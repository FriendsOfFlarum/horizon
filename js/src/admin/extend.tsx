import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import LinkButton from 'flarum/common/components/LinkButton';
import { lockable } from './lockable';

const trans = (key: string) => app.translator.trans(`fof-horizon.admin.settings.${key}`);

export default [
  new Extend.Admin()
    .customSetting(
      () => (
        <div className="HorizonSettings-dashboardLink">
          <LinkButton
            icon="fas fa-external-link-alt"
            className="Button"
            href={app.forum.attribute('adminUrl') + '/horizon'}
            external={true}
            target="_blank"
          >
            {app.translator.trans('fof-horizon.admin.stats.full_dashboard')}
          </LinkButton>
        </div>
      ),
      100
    )
    .section(() => ({ id: 'mail', icon: 'fas fa-envelope', label: trans('mail_title'), description: trans('mail_help') }), 30)
    .section(() => ({ id: 'trim', icon: 'fas fa-broom', label: trans('trim_title'), description: trans('trim_help') }), 20)
    .section(() => ({ id: 'workers', icon: 'fas fa-server', label: trans('supervisor_title'), description: trans('supervisor_help') }), 10)
    .setting(
      () =>
        lockable('email_concurrency', {
          type: 'number',
          section: 'mail',
          min: 1,
          setting: 'fof-horizon.email_concurrency',
          label: trans('email_concurrency'),
          help: trans('email_concurrency_help'),
          placeholder: 1,
        }),
      95
    )
    .setting(
      () =>
        lockable('trim.recent', {
          type: 'number',
          section: 'trim',
          setting: 'fof-horizon.trim.recent',
          label: trans('trim_recent'),
          help: trans('trim_recent_help'),
        }),
      89
    )
    .setting(
      () =>
        lockable('trim.pending', {
          type: 'number',
          section: 'trim',
          setting: 'fof-horizon.trim.pending',
          label: trans('trim_pending'),
          help: trans('trim_pending_help'),
        }),
      88
    )
    .setting(
      () =>
        lockable('trim.completed', {
          type: 'number',
          section: 'trim',
          setting: 'fof-horizon.trim.completed',
          label: trans('trim_completed'),
          help: trans('trim_completed_help'),
        }),
      87
    )
    .setting(
      () =>
        lockable('trim.recent_failed', {
          type: 'number',
          section: 'trim',
          setting: 'fof-horizon.trim.recent_failed',
          label: trans('trim_recent_failed'),
          help: trans('trim_recent_failed_help'),
        }),
      86
    )
    .setting(
      () =>
        lockable('trim.failed', {
          type: 'number',
          section: 'trim',
          setting: 'fof-horizon.trim.failed',
          label: trans('trim_failed'),
          help: trans('trim_failed_help'),
        }),
      85
    )
    .setting(
      () =>
        lockable('trim.monitored', {
          type: 'number',
          section: 'trim',
          setting: 'fof-horizon.trim.monitored',
          label: trans('trim_monitored'),
          help: trans('trim_monitored_help'),
        }),
      84
    )
    .setting(
      () =>
        lockable('memory_limit', {
          type: 'number',
          section: 'workers',
          setting: 'fof-horizon.memory_limit',
          label: trans('memory_limit'),
          help: trans('memory_limit_help'),
        }),
      64
    ),
];
