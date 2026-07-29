import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import MailPage from 'flarum/admin/components/MailPage';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import { lockable } from './lockable';

/**
 * Surface the email-concurrency control on core's Mail admin page as well as on
 * the Horizon page. "How many emails send at once" is something an admin looks
 * for under Email first, so we implant the same setting there — bound to the
 * same `fof-horizon.email_concurrency` setting, and honouring the same lock
 * state (disabled + explained when pinned by env / config.php / extend.php).
 *
 * Only shown when Horizon is actually driving the queue; on the database queue
 * this setting has no effect, so it would be misleading.
 */
export default function extendMailPage() {
  extend(MailPage.prototype, 'mailSettingItems', function (this: MailPage, items: ItemList<Mithril.Children>) {
    // Horizon owns email throughput only when it's actually driving the queue.
    // AdminContent labels the driver "Redis + Horizon" in that case.
    if (app.data.queueDriver !== 'Redis + Horizon') {
      return;
    }

    items.add(
      'fof-horizon-email-concurrency',
      this.buildSettingComponent(
        lockable('email_concurrency', {
          type: 'number',
          min: 1,
          setting: 'fof-horizon.email_concurrency',
          label: app.translator.trans('fof-horizon.admin.settings.email_concurrency'),
          help: app.translator.trans('fof-horizon.admin.settings.email_concurrency_help'),
          placeholder: 1,
        })
      ),
      // Just below the built-in addresses/format/driver fields.
      50
    );
  });
}
