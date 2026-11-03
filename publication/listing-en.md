# Marketplace listing — English (the version to submit)

## Title (5 words at most, no "Magento", "Adobe", "M2", "Module", "Extension")

Pathly Checkout Monitoring

## Short description

Know before your customers when your checkout breaks. Ping checks that the shop answers, Flow replays the purchase path up to the payment page, after every update.

## Long description

**The extension is free. It connects to a Pathly account: the Solo plan is free, without credit card. Paid Pathly plans are a separate subscription (prices on pathlyhq.com).**

### The problem

The server answers, the home page loads, and yet nobody can order: an updated module broke the "Place Order" button, the cart no longer fills up, the payment page stays blank. An uptime check does not see it. You learn it from a customer, or from falling sales.

### What the extension does

Pathly watches the path. Ping if it's up. Flow if a user can walk it. Chain if the services behind it hold.

- **Ping**: as soon as you connect, Pathly checks every 5 minutes that your shop answers. A Ping that already exists for the shop is attached, without duplicate.
- **Flow prepared by Lumi**: Lumi, the assistant of Pathly, recognizes Magento and prepares the Flow of your checkout: add a product to the cart, then go to the payment page. It also proposes the customer sign-in and the contact form. Each proposed Flow is replayed before it is shown to you. You validate it in one click.
- **Replay after an update**: after `setup:upgrade`, or when Magento or a module changes version, Pathly replays the Flow as soon as the maintenance mode is off. Within minutes, you know whether the update broke the checkout.
- **Dashboard panel**: state of the Ping and of the Flow, date and screenshot of the last failure, plan and quotas, link to Pathly.
- **Availability badge** in the footer or as a widget, linked to your public status page. Off by default.

### Lumi does nothing behind your back

It pays nothing, submits no form and only types test values. You validate each Flow before it is saved.

### Data and security

- Nothing is sent to Pathly before you save an API key.
- Then, only the address of the shop and the versions of Magento and of the extension are sent. No customer data, no order.
- The API key is encrypted by Magento, never shown again.
- The badge is served by your shop: your visitors load nothing from Pathly.
- Screens reserved to authorized roles (Magento access control).
- We apply the GDPR. App, API, workers and screenshots: Google Cloud europe-west1. A DPA is available.

### Good to know

Each run of the Flow creates a test cart on the shop. It can trigger your abandoned cart reminders.

### Compatibility

Magento Open Source and Adobe Commerce 2.4.6, 2.4.7 and 2.4.8, PHP 8.1 to 8.4 depending on the Magento version. The Magento cron must run. Multi-store: the extension monitors the default store view.

## FAQ

**Do I need a Pathly account?**
Yes. The extension offers to create a free account, without credit card. Then you paste an API key.

**Which API key should I create?**
In Pathly, Settings, then API keys. Preferably a key limited to a Space, with the scopes `org:read scenarios:read scenarios:write runs:read runs:trigger assistant:run assistant:save status:read status:write`.

**Does the Flow place real orders?**
No. It stops before payment and only types test values. It does create a test cart on each run.

**What happens after a module update?**
Magento runs the extension at the end of each `setup:upgrade`, and the cron compares the installed versions every 5 minutes. Pathly then replays the Flow, once the maintenance mode is off. Two replays are at least 10 minutes apart.

**Do my customers see anything?**
Only if you turn the badge on: a small image in the footer, served by your shop, linking to your status page.

**What if I uninstall the extension?**
Its settings, state and scheduled jobs are deleted from the shop. Your Ping and Flows stay in your Pathly account, where you can delete them.

**Does Lumi recognize every shop?**
It recognizes Magento. If your theme is heavily customized and Lumi does not find the checkout, you can record the Flow yourself in Pathly, without code, then type its identifier in "Other Flows to replay".

## Release notes (text, for the technical submission)

1.0.0
- Connection with an API key, link to create a free account.
- Ping of the shop created or attached automatically.
- Flow of the checkout prepared by Lumi, validated in one click.
- Replay of the Flows after setup:upgrade or a version change of Magento or of a module.
- Dashboard panel: state, last failure with screenshot, plan and quotas.
- Availability badge, off by default, served by the shop.
- Translations: French, English, Spanish, German.

## Categories (one main, up to three sub)

- Main: Site Management (or the closest "Site Monitoring" category offered by the form).
- Sub: Reporting & Analytics, Customer Experience.

## Compatibility to tick

Magento Open Source 2.4.6, 2.4.7, 2.4.8. Adobe Commerce (on-premises) 2.4.6, 2.4.7, 2.4.8. Adobe Commerce on cloud: same versions, not tested on the cloud infrastructure.
