import app from 'flarum/admin/app';
import extendStatusWidget from './extendStatusWidget';
import extendDashboardPage from './extendDashboardPage';
import SettingsPage from './components/SettingsPage';

export { default as extend } from './extend';

app.initializers.add('fof-horizon', () => {
  SettingsPage.register();
  extendStatusWidget();
  extendDashboardPage();
});
