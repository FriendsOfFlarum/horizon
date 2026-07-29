import app from 'flarum/admin/app';
import extendStatusWidget from './extendStatusWidget';
import extendDashboardPage from './extendDashboardPage';
import extendMailPage from './extendMailPage';

export { default as extend } from './extend';

app.initializers.add('fof-horizon', () => {
  extendStatusWidget();
  extendDashboardPage();
  extendMailPage();
});
