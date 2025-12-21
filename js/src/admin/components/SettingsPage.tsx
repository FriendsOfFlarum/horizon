import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import LinkButton from 'flarum/common/components/LinkButton';

export default class SettingsPage extends ExtensionPage {
  static register() {
    app.registry.for('fof-horizon');

    app.registry.registerSetting({
      type: 'number',
      setting: 'fof-horizon.trim.recent',
      label: app.translator.trans('fof-horizon.admin.settings.trim_recent'),
      help: app.translator.trans('fof-horizon.admin.settings.trim_recent_help'),
    });

    app.registry.registerSetting({
      type: 'number',
      setting: 'fof-horizon.trim.pending',
      label: app.translator.trans('fof-horizon.admin.settings.trim_pending'),
      help: app.translator.trans('fof-horizon.admin.settings.trim_pending_help'),
    });

    app.registry.registerSetting({
      type: 'number',
      setting: 'fof-horizon.trim.completed',
      label: app.translator.trans('fof-horizon.admin.settings.trim_completed'),
      help: app.translator.trans('fof-horizon.admin.settings.trim_completed_help'),
    });

    app.registry.registerSetting({
      type: 'number',
      setting: 'fof-horizon.trim.recent_failed',
      label: app.translator.trans('fof-horizon.admin.settings.trim_recent_failed'),
      help: app.translator.trans('fof-horizon.admin.settings.trim_recent_failed_help'),
    });

    app.registry.registerSetting({
      type: 'number',
      setting: 'fof-horizon.trim.failed',
      label: app.translator.trans('fof-horizon.admin.settings.trim_failed'),
      help: app.translator.trans('fof-horizon.admin.settings.trim_failed_help'),
    });

    app.registry.registerSetting({
      type: 'number',
      setting: 'fof-horizon.trim.monitored',
      label: app.translator.trans('fof-horizon.admin.settings.trim_monitored'),
      help: app.translator.trans('fof-horizon.admin.settings.trim_monitored_help'),
    });
  }

  content() {
    const horizonUrl = app.forum.attribute('adminUrl') + '/horizon';
    return (
      <div className="container">
        <div className="HorizonSettingsPage">
          <LinkButton icon="fas fa-external-link-alt" className="Button" href={horizonUrl} external={true} target="_blank">
            {app.translator.trans('fof-horizon.admin.stats.full_dashboard')}
          </LinkButton>
          <hr />
          <div className="HorizonSettingsPage-settings">
            <div className="Form-group">
              <div className="HorizonSettingsPage-trim">
                <h3>{app.translator.trans('fof-horizon.admin.settings.trim_title')}</h3>
                <p className="helpText">{app.translator.trans('fof-horizon.admin.settings.trim_help')}</p>
                {this.buildSettingComponent({
                  setting: 'fof-horizon.trim.recent',
                  type: 'number',
                  label: app.translator.trans('fof-horizon.admin.settings.trim_recent'),
                  help: app.translator.trans('fof-horizon.admin.settings.trim_recent_help'),
                })}
                {this.buildSettingComponent({
                  setting: 'fof-horizon.trim.pending',
                  type: 'number',
                  label: app.translator.trans('fof-horizon.admin.settings.trim_pending'),
                  help: app.translator.trans('fof-horizon.admin.settings.trim_pending_help'),
                })}
                {this.buildSettingComponent({
                  setting: 'fof-horizon.trim.completed',
                  type: 'number',
                  label: app.translator.trans('fof-horizon.admin.settings.trim_completed'),
                  help: app.translator.trans('fof-horizon.admin.settings.trim_completed_help'),
                })}
                {this.buildSettingComponent({
                  setting: 'fof-horizon.trim.recent_failed',
                  type: 'number',
                  label: app.translator.trans('fof-horizon.admin.settings.trim_recent_failed'),
                  help: app.translator.trans('fof-horizon.admin.settings.trim_recent_failed_help'),
                })}
                {this.buildSettingComponent({
                  setting: 'fof-horizon.trim.failed',
                  type: 'number',
                  label: app.translator.trans('fof-horizon.admin.settings.trim_failed'),
                  help: app.translator.trans('fof-horizon.admin.settings.trim_failed_help'),
                })}
                {this.buildSettingComponent({
                  setting: 'fof-horizon.trim.monitored',
                  type: 'number',
                  label: app.translator.trans('fof-horizon.admin.settings.trim_monitored'),
                  help: app.translator.trans('fof-horizon.admin.settings.trim_monitored_help'),
                })}
              </div>
              <br />
              {this.submitButton()}
            </div>
          </div>
        </div>
      </div>
    );
  }
}
