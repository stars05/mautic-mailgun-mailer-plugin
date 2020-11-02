<?php
/*
 * @copyright   2020. All rights reserved
 * @author      Stanislav Denysenko<stascrack@gmail.com>
 *
 * @link        https://github.com/stars05
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

return [
    'name' => 'Mautic Mailgun Mailer Bundle',
    'description' => 'Integrate Swiftmailer transport for Mailgun API',
    'author' => 'Stanislav Denysenko',
    'version' => '1.0.0',

    'services' => [
        'other' => [
            'mautic.transport.mailgun_api' => [
                'class' => \MauticPlugin\MauticMailgunMailerBundle\Swiftmailer\Transport\MailgunApiTransport::class,
                'serviceAlias' => 'swiftmailer.mailer.transport.%s',
                'arguments' => [
                    'mautic.email.model.transport_callback',
                    'mautic.http.client',
                    'translator',
                    '%mautic.mailer_mailgun_max_batch_limit%',
                    '%mautic.mailer_mailgun_batch_recipient_count%',
                    '%mautic.mailer_mailgun_webhook_signing_key%',
                ],
                'methodCalls' => [
                    'setApiKey' => ['%mautic.mailer_api_key%'],
                    'setDomain' => ['%mautic.mailer_host%',],
                    'setRegion' => ['%mautic.mailer_mailgun_region%'],
                ],
                'tag' => 'mautic.email_transport',
                'tagArguments' => [
                    \Mautic\EmailBundle\Model\TransportType::TRANSPORT_ALIAS => 'mautic.email.config.mailer_transport.mailgun_api',
                    \Mautic\EmailBundle\Model\TransportType::FIELD_HOST => true,
                    \Mautic\EmailBundle\Model\TransportType::FIELD_API_KEY => true,
                ],
            ],
        ],
    ],
    'parameters' => [
        'mailer_mailgun_max_batch_limit' => 300,
        'mailer_mailgun_batch_recipient_count' => 5,
        'mailer_mailgun_region' => 'us',
        'mailer_mailgun_webhook_signing_key' => null,
    ],
];
