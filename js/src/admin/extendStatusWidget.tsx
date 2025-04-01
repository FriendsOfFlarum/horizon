import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import StatusWidget from 'flarum/admin/components/StatusWidget';

export default function extendStatusWidget() {
  extend(StatusWidget.prototype, 'items', function (items) {
    const cacheStore = app.data.cacheStore;
    const cacheVersion = app.data.cacheVersion;

    items.add('version-cache', [<strong>Cache store</strong>, <br />, `${cacheStore} ${cacheVersion}`], 75);
  });
}
