import app from 'flarum/admin/app';
import extendDashboardPage from './extendDashboardPage';
import extendMailPage from './extendMailPage';

export { default as extend } from './extend';

app.initializers.add('fof-horizon', () => {
  extendDashboardPage();
  extendMailPage();
});
