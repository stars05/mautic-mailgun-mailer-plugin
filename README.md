# Mailgun API plugin for Mautic v3

## Instalation
 - upload the contents in this repo to mautic instalation `plugins/MauticMailgunMailerBundle`
 - remove cache `sudo rm -rf var/cache/*`
 - go to mautic settings > plugins > click `Install / Upgrade Plugin`
 - done.
 
 ## Usage
 
 - Choose Mailgun as the mail service, in mautic mail configuration > Email Settings.
 
 Enter yours:
 - host(is domain): From your admin panel in Mailgin
 - API Key From your admin panel in Mailgin 

### Add webhook URL to mailgun

Add `https://mautic.loc/mailer/mailgun_api/callback` in the mailgun webhook for your selected events:
- permanent failure
- spam complaints
- temporary failure
- unsubscribes

Now your mautic will be able to send through mailgun API and track email events such as bounce, failed, unsubscribe, spam according to the webhook you set in mailgun.

### Mailgun SMTP
If you need to send messages with mailun smtp, see:
[Mailgun plugin for Mautic (AFMailgun)](https://github.com/azamuddin/mautic-mailgun-plugin "Mailgun plugin for Mautic (AFMailgun)")


## Author

Stanislav Denysenko

stascrack@gmail.com
