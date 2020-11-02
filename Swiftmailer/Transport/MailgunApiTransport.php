<?php

namespace MauticPlugin\MauticMailgunMailerBundle\Swiftmailer\Transport;

use GuzzleHttp\Client;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\EmailBundle\Swiftmailer\Transport\AbstractTokenArrayTransport;
use Mautic\EmailBundle\Swiftmailer\Transport\CallbackTransportInterface;
use Mautic\LeadBundle\Entity\DoNotContact;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;

class MailgunApiTransport extends AbstractTokenArrayTransport implements \Swift_Transport, CallbackTransportInterface
{
    private const HOST = 'api.%region_dot%mailgun.net';

    /**
     * @var int
     */
    private $maxBatchLimit;
    /**
     * @var int|null
     */
    private $batchRecipientCount;
    /**
     * @var Client
     */
    private $client;
    /**
     * @var string
     */
    private $apiKey;
    /**
     * @var string
     */
    private $domain;
    /**
     * @var string
     */
    private $region;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var TransportCallback
     */
    private $transportCallback;

    public function __construct(TransportCallback $transportCallback, Client $client, TranslatorInterface $translator, int $maxBatchLimit, ?int $batchRecipientCount)
    {
        $this->transportCallback = $transportCallback;
        $this->client = $client;
        $this->translator = $translator;
        $this->maxBatchLimit = $maxBatchLimit;
        $this->batchRecipientCount = $batchRecipientCount ?: 5;
    }

    public function setApiKey(?string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setDomain(?string $domain): void
    {
        $this->domain = $domain;
    }

    public function setRegion(?string $region): void
    {
        $this->region = $region;
    }

    public function start(): void
    {
        if (empty($this->apiKey)) {
            $this->throwException($this->translator->trans('mautic.email.api_key_required', [], 'validators'));
        }

        $this->started = true;
    }

    /**
     * @param null $failedRecipients
     *
     * @return int
     *
     * @throws \Exception
     */
    public function send(\Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $count = 0;
        $failedRecipients = (array) $failedRecipients;

        if ($evt = $this->getDispatcher()->createSendEvent($this, $message)) {
            $this->getDispatcher()->dispatchEvent($evt, 'beforeSendPerformed');
            if ($evt->bubbleCancelled()) {
                return 0;
            }
        }

        try {
            $count = $this->getBatchRecipientCount($message);

            $preparedMessage = $this->getMessage($message);

            $payload = $this->getPayload($preparedMessage);

            $endpoint = sprintf('%s/v3/%s/messages', $this->getEndpoint(), urlencode($this->domain));

            $response = $this->client->request(
                'POST',
                'https://'.$endpoint, [
                    'auth' => ['api', $this->apiKey, 'basic'],
                    'headers' => $preparedMessage['headers'],
                    'form_params' => $payload,
                ]
            );

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                if ('application/json' === $response->getHeaders(false)['content-type'][0]) {
                    $result = $response->toArray(false);
                    throw new TransportException('Unable to send an email: '.$result['message'].sprintf(' (code %d).', $response->getStatusCode()), $response);
                }

                throw new TransportException('Unable to send an email: '.$response->getContent(false).sprintf(' (code %d).', $response->getStatusCode()), $response);
            }

            if ($evt) {
                $evt->setResult(\Swift_Events_SendEvent::RESULT_SUCCESS);
                $evt->setFailedRecipients($failedRecipients);
                $this->getDispatcher()->dispatchEvent($evt, 'sendPerformed');
            }

            return $count;
        } catch (\Exception $e) {
            $this->triggerSendError($evt, $failedRecipients);
            $message->generateId();
            $this->throwException($e->getMessage());
        }

        return $count;
    }

    /**
     * Return the max number of to addresses allowed per batch.  If there is no limit, return 0.
     *
     * @see https://help.mailgun.com/hc/en-us/articles/203068914-What-Are-the-Differences-Between-the-Free-and-Flex-Plans-
     *      there is limit depending on your account, and you can change it in configuration for this plugin
     *      Free plan requires 300 messages per day
     *
     * @return int
     */
    public function getMaxBatchLimit(): int
    {
        return $this->maxBatchLimit;
    }

    /**
     * Get the count for the max number of recipients per batch.
     *
     * @see https://help.mailgun.com/hc/en-us/articles/203068914-What-Are-the-Differences-Between-the-Free-and-Flex-Plans-
     *      5 Authorized Recipients for free plan and no limit for Flex Plan
     *
     * @param int    $toBeAdded Number of emails about to be added
     * @param string $type      Type of emails being added (to, cc, bcc)
     *
     * @return int
     */
    public function getBatchRecipientCount(\Swift_Message $message, $toBeAdded = 1, $type = 'to'): int
    {
        $toCount = is_countable($message->getTo()) ? count($message->getTo()) : 0;
        $ccCount = is_countable($message->getCc()) ? count($message->getCc()) : 0;
        $bccCount = is_countable($message->getBcc()) ? count($message->getBcc()) : 0;

        return null === $this->batchRecipientCount ? $this->batchRecipientCount : $toCount + $ccCount + $bccCount + $toBeAdded;
    }

    /**
     * Returns a "transport" string to match the URL path /mailer/{transport}/callback.
     *
     * @return mixed
     */
    public function getCallbackPath()
    {
        return 'mailgun_api';
    }

    /**
     * Processes the response.
     */
    public function processCallbackRequest(Request $request)
    {
        $postData = json_decode($request->getContent(), true);


        if (!isset($postData['event-data'])) {
            // response must be an array
            return null;
        }

        $event = $postData['event-data'];

        if (!in_array($event['event'], ['bounce', 'rejected', 'complained', 'unsubscribed', 'permanent_fail', 'failed'])) {
            return;
        }

        $reason = $event['event'];
        $type = DoNotContact::IS_CONTACTABLE;

        if ($event['event'] === 'bounce' || $event['event'] === 'rejected' || $event['event'] === 'permanent_fail' || $event['event'] === 'failed') {
            if (!empty($event['delivery-status']['message'])) {
                $reason = $event['delivery-status']['message'];
            } elseif (!empty($event['delivery-status']['description'])) {
                $reason = $event['delivery-status']['description'];
            }
            $type = DoNotContact::BOUNCED;
        } elseif ($event['event'] === 'complained') {
            if (isset($event['delivery-status']['message'])) {
                $reason = $event['delivery-status']['message'];
            }
            $type = DoNotContact::UNSUBSCRIBED;
        } elseif ($event['event'] === 'unsubscribed') {
            $reason = 'User unsubscribed';
            $type = DoNotContact::UNSUBSCRIBED;
        }

        $customId = isset($event['user-variables']['custom_id']) ? $event['user-variables']['custom_id'] : null;
        if (null !== $customId && '' !== $customId && false !== strpos($customId, '-', 0)) {
            $fistDashPos = strpos($customId, '-', 0);
            $leadIdHash = substr($customId, 0, $fistDashPos);
            $leadEmail = substr($customId, $fistDashPos + 1, strlen($customId));
            if ($event['recipient'] === $leadEmail) {
                $this->transportCallback->addFailureByHashId($leadIdHash, $reason, $type);
            }

            return;
        }

        $this->transportCallback->addFailureByAddress($event['recipient'], $reason, $type);
    }

    /**
     * @param array $failedRecipients
     */
    private function triggerSendError(\Swift_Events_SendEvent $evt, &$failedRecipients): void
    {
        $failedRecipients = array_merge(
            $failedRecipients,
            array_keys((array) $this->message->getTo()),
            array_keys((array) $this->message->getCc()),
            array_keys((array) $this->message->getBcc())
        );

        if ($evt) {
            $evt->setResult(\Swift_Events_SendEvent::RESULT_FAILED);
            $evt->setFailedRecipients($failedRecipients);
            $this->getDispatcher()->dispatchEvent($evt, 'sendPerformed');
        }
    }

    private function getEndpoint(): ?string
    {
        return str_replace('%region_dot%', 'us' !== ($this->region ?: 'us') ? $this->region.'.' : '', self::HOST);
    }

    /**
     * @return array|\Swift_Message
     */
    private function getMessage($message)
    {
        $this->message = $message;
        $metadata = $this->getMetadata();

        // Mailgun uses {{ name }} for tokens so Mautic's need to be converted; although using their {{{ }}} syntax to prevent HTML escaping
        if (!empty($metadata)) {
            $metadataSet = reset($metadata);
            $tokens = (!empty($metadataSet['tokens'])) ? $metadataSet['tokens'] : [];
            $mauticTokens = array_keys($tokens);

            $mergeVars = $mergeVarPlaceholders = [];
            foreach ($mauticTokens as $token) {
                $mergeVars[$token] = strtoupper(preg_replace('/[^a-z0-9]+/i', '', $token));
                $mergeVarPlaceholders[$token] = '{{{ '.$mergeVars[$token].' }}}';
            }
        }

        $messageArray = $this->messageToArray($mauticTokens, $mergeVarPlaceholders, true);;
        if (isset($message->leadIdHash)) {
            // contact leadidHeash and email to be sure not applying email stat to bcc
            $messageArray['custom'] = ['v:custom_id' => $message->leadIdHash.'-'.key($message->getTo())];
        }

        return $messageArray;
    }

    private function getPayload($message): array
    {
        $payload = [
            'from' => sprintf('%s <%s>', $message['from']['name'], $message['from']['email']),
            'to' => $this->prepareRecipients($message['recipients']['to']),
            'subject' => $message['subject'],
            'html' => $message['html'],
            'text' => $message['text'],
            'attachment' => $message['attachments'],
        ];

        if (!empty($message['recipients']['cc'])) {
            $payload['cc'] = $this->prepareRecipients($message['recipients']['cc']);
        }

        if (!empty($message['recipients']['bcc'])) {
            $payload['bcc'] = $this->prepareRecipients($message['recipients']['bcc']);
        }

        if (!empty($message['custom'])) {
            $payload = array_merge($payload, $message['custom']);
        }

        return $payload;
    }

    private function prepareRecipients(array $recipients): string
    {
        return implode(',', array_keys($recipients));
    }
}
