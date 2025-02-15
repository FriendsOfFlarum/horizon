import app from 'flarum/admin/app';
import SettingsPage from './components/SettingsPage';
import extendStatusWidget from './extendStatusWidget';
import extendDashboardPage from './extendDashboardPage';

app.initializers.add('fof/horizon', () => {
  app.registry.for('fof-horizon').registerPage(SettingsPage);
  extendStatusWidget();
  extendDashboardPage();
});
