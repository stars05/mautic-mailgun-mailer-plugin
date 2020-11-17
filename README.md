# Mailgun API plugin for Mautic v2

## Instalation
 - upload the contents in this repo to mautic instalation `plugins/MauticMailgunMailerBundle`
 - change zone if needed, go to plugins/MauticMailgunMailerBundle/Config/config.php file and change mailer_mailgun_region parameter(us by default or eu)
 - if you want to change batch send email limit, go to plugins/MauticMailgunMailerBundle/Config/config.php file and change mailer_mailgun_batch_recipient_count parameter to needed number of batch count
 - to receive webhooks, add your api key to config, go to plugins/MauticMailgunMailerBundle/Config/config.php file and change mailer_mailgun_webhook_signing_key to your webhook api key from mailgun admin panel
 - remove cache `sudo rm -rf var/cache/*`
 - go to mautic settings > plugins > click `Install / Upgrade Plugin`
 - done.
 
 ## Usage
 
 - Choose Mailgun Api as the mail service, in mautic mail configuration > Email Settings.
 
 Enter yours:
 - host(is domain): From your admin panel in Mailgin Api
 - API Key From your admin panel in Mailgin Api

### Add webhook URL to mailgun

Add `https://mautic.loc/mailer/mailgun_api/callback` in the mailgun webhook for your selected events:
- permanent failure
- spam complaints
- temporary failure
- unsubscribes

Now your mautic will be able to send through mailgun API and track email events such as bounce, failed, unsubscribe, spam according to the webhook you set in mailgun.

Mailgun attachments in process.....

### Mailgun SMTP
If you need to send messages with mailgun smtp, see:
[Mailgun plugin for Mautic (AFMailgun)](https://github.com/azamuddin/mautic-mailgun-plugin "Mailgun plugin for Mautic (AFMailgun)")


## Author

Stanislav Denysenko

stascrack@gmail.com
