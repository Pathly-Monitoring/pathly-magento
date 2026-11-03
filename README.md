# Pathly for Magento

Know before your customers when your checkout breaks.

*Pathly watches the path. Ping if it's up. Flow if a user can walk it. Chain if the services behind it hold.*

The server answers, the home page loads, and yet nobody can order: an updated module broke the "Place order" button, the cart stays empty, the payment page is blank. An uptime check does not see it. This module connects your Magento shop to [Pathly](https://pathlyhq.com):

- **Ping**: as soon as the module is connected, Pathly checks every 5 minutes that the shop answers. A Ping that already exists for the shop is attached, without duplicate.
- **Flow of the checkout, prepared by Lumi**: Lumi, the assistant of Pathly, recognizes Magento and prepares the Flow of your checkout: add a product to the cart, then go to the payment page. Lumi also proposes the customer sign-in and the contact form. Each proposed Flow is replayed before it is shown to you. You validate it in one click.
- **Replay after each update**: after `bin/magento setup:upgrade`, or when Magento or a module changes version, Pathly replays your Flows once the maintenance mode is off.
- **Dashboard panel**: state of the Ping and of the Flows, date and screenshot of the last failure, plan and quotas, link to Pathly.
- **Availability badge**, off by default, linked to your public status page.

Lumi pays nothing, submits no form and only types test values. You validate each Flow before it is saved.

## Requirements

| | |
|---|---|
| Magento Open Source or Adobe Commerce | 2.4.6, 2.4.7, 2.4.8, 2.4.9 |
| PHP | 8.1 to 8.5, as supported by your Magento version |
| Pathly account | free Solo plan, without credit card: [create an account](https://pathlyhq.com/fr/login?mode=signup&ref=magento&utm_source=magento&utm_medium=module) |

## Installation

With Composer, from the Adobe Commerce Marketplace once the module is listed there:

```bash
composer require pathly/module-monitoring
bin/magento module:enable Pathly_Monitoring
bin/magento setup:upgrade
bin/magento cache:flush
```

Or from the archive of a [GitHub release](https://github.com/Pathly-Monitoring/pathly-magento/releases): unzip it into `app/code/Pathly/Monitoring`, then run the last three commands.

## Configuration

1. In Pathly: Settings, API keys. Create a key, preferably limited to a Space, with the scopes `org:read scenarios:read scenarios:write runs:read runs:trigger assistant:run assistant:save status:read status:write`.
2. In Magento: **Stores > Configuration > Pathly > Monitoring**. Paste the key and save. The module checks it with Pathly, Magento stores it encrypted, and it is never shown again. The Ping of the shop is created or attached at once.
3. **Flow of the checkout**: tick "I manage this shop", then click "Prepare the Flow with Lumi". After one or two minutes, validate the Flow you want.
4. Optional: **Availability badge**. Turning it on publishes your Pathly status page. Show it in the footer, or place the "Pathly availability badge" widget (Content > Widgets).

The same settings work from the command line:

```bash
bin/magento config:set pathly_monitoring/connection/api_key sp_...
bin/magento pathly:sync            # what the cron does, now
bin/magento pathly:sync --replay   # replay the Flows of the shop now
```

Access is controlled by roles (System > Permissions > User Roles): `Pathly_Monitoring::config` for the settings, `Pathly_Monitoring::lumi` for Lumi, `Pathly_Monitoring::dashboard` for the panel.

## Replay after an update

Two signals ask for a replay:

- every `bin/magento setup:upgrade` (the `Setup/RecurringData` class, run by Magento at the end of each upgrade);
- a change of the fingerprint of the installed versions (Magento version and edition, enabled modules and the version of their package), checked by the cron job `pathly_monitoring_sync` every 5 minutes.

The replay waits for the end of the maintenance mode, then calls `POST /v1/scenarios/{id}/run` for each Flow saved with Lumi from the module, and for the Flows typed in "Other Flows to replay". Two replays are at least 10 minutes apart. The Magento cron must run.

## Good to know: test carts

Each run of a Flow creates a test cart on the shop, and may sign in a test customer. It can trigger your abandoned cart reminders. Exclude the test customer or the test address from them.

## Data and security

- Nothing is sent to Pathly before an administrator saves an API key.
- Then, only the address of the shop (secure base URL of the default store view) and the versions of Magento and of the module (in the `User-Agent` header) are sent. No customer data, no order.
- The module only calls `https://api.pathlyhq.com`. It never calls an address typed by a user.
- The API key is stored encrypted by Magento (`obscure` field, marked sensitive: it is not exported by `app:config:dump`), never shown again, never written to the logs.
- Admin screens and actions are reserved to the roles above, with the form key of Magento.
- The badge is a copy kept by the shop and served by the shop (`/pathly/badge/svg`): a visitor of the shop loads nothing from Pathly.
- The screenshot of the last failure is a link signed by Pathly, valid 15 minutes, loaded by the administrator's browser only.
- Pathly applies the GDPR. App, API, workers and screenshots: Google Cloud europe-west1. A DPA is available.

## Uninstallation

Installed with Composer:

```bash
bin/magento module:uninstall -r Pathly_Monitoring
```

Copied into `app/code`:

```bash
bin/magento module:disable Pathly_Monitoring
bin/magento module:uninstall --non-composer Pathly_Monitoring
rm -rf app/code/Pathly/Monitoring
bin/magento setup:upgrade
```

Both delete the settings, the state of the module, its scheduled jobs and its badge widgets. Your Ping and Flows stay in your Pathly account, where you can delete them.

## Development

Unit tests run against the sources of Magento Open Source (tag of `github.com/magento/magento2`, dependencies from Packagist, no repo.magento.com key):

```bash
dev/unit.sh 8.3 2.4.8-p3 --coverage   # Docker: PHPUnit, 100 % of lines and branches required
dev/unit.sh 8.1 2.4.6-p15 --coverage
```

The integration test installs Magento 2.4.8 in Docker, with the Pathly API simulated behind `https://api.pathlyhq.com`: installation, connection, Ping, Lumi, dashboard, replay after `setup:upgrade`, badge, uninstallation.

```bash
sh dev/build.sh            # in a PHP container with Composer: build/pathly-module-monitoring-<version>.zip
dev/integration/run.sh     # about 3 GB of memory
```

The CI (GitHub Actions) runs the Magento Coding Standard, the unit tests on PHP 8.1 (Magento 2.4.6) 8.3 (2.4.7 and 2.4.8) and 8.5 (2.4.9) with the coverage check, the archive and the integration test.

## License

[OSL-3.0](LICENSE.txt). Questions: simon@pathlyhq.com.
