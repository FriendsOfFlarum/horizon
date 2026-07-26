import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import LinkButton from 'flarum/common/components/LinkButton';

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
          <hr />
        </div>
      ),
      100
    )
    .customSetting(
      () => (
        <div>
          <h3>{trans('trim_title')}</h3>
          <p className="helpText">{trans('trim_help')}</p>
        </div>
      ),
      90
    )
    .setting(
      () => ({
        type: 'number',
        setting: 'fof-horizon.trim.recent',
        label: trans('trim_recent'),
        help: trans('trim_recent_help'),
      }),
      89
    )
    .setting(
      () => ({
        type: 'number',
        setting: 'fof-horizon.trim.pending',
        label: trans('trim_pending'),
        help: trans('trim_pending_help'),
      }),
      88
    )
    .setting(
      () => ({
        type: 'number',
        setting: 'fof-horizon.trim.completed',
        label: trans('trim_completed'),
        help: trans('trim_completed_help'),
      }),
      87
    )
    .setting(
      () => ({
        type: 'number',
        setting: 'fof-horizon.trim.recent_failed',
        label: trans('trim_recent_failed'),
        help: trans('trim_recent_failed_help'),
      }),
      86
    )
    .setting(
      () => ({
        type: 'number',
        setting: 'fof-horizon.trim.failed',
        label: trans('trim_failed'),
        help: trans('trim_failed_help'),
      }),
      85
    )
    .setting(
      () => ({
        type: 'number',
        setting: 'fof-horizon.trim.monitored',
        label: trans('trim_monitored'),
        help: trans('trim_monitored_help'),
      }),
      84
    )
    .customSetting(
      () => (
        <div>
          <h3>{trans('supervisor_title')}</h3>
          <p className="helpText">{trans('supervisor_help')}</p>
        </div>
      ),
      70
    )
    .setting(
      () => ({
        type: 'number',
        setting: 'fof-horizon.supervisor.processes',
        label: trans('supervisor_processes'),
        help: trans('supervisor_processes_help'),
      }),
      69
    )
    .setting(
      () => ({
        type: 'number',
        setting: 'fof-horizon.supervisor.memory',
        label: trans('supervisor_memory'),
        help: trans('supervisor_memory_help'),
      }),
      68
    )
    .setting(
      () => ({
        type: 'number',
        setting: 'fof-horizon.supervisor.tries',
        label: trans('supervisor_tries'),
        help: trans('supervisor_tries_help'),
      }),
      67
    )
    .setting(
      () => ({
        type: 'text',
        setting: 'fof-horizon.supervisor.queues',
        label: trans('supervisor_queues'),
        help: trans('supervisor_queues_help'),
      }),
      66
    )
    .setting(
      () => ({
        type: 'select',
        setting: 'fof-horizon.supervisor.balance',
        options: {
          auto: 'auto',
          simple: 'simple',
          false: 'off',
        },
        default: 'auto',
        label: trans('supervisor_balance'),
        help: trans('supervisor_balance_help'),
      }),
      65
    )
    .setting(
      () => ({
        type: 'number',
        setting: 'fof-horizon.memory_limit',
        label: trans('memory_limit'),
        help: trans('memory_limit_help'),
      }),
      64
    ),
];
