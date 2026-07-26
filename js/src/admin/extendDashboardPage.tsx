import { extend } from 'flarum/common/extend';
import type Mithril from 'mithril';

import DashboardPage from 'flarum/admin/components/DashboardPage';
import ItemList from 'flarum/common/utils/ItemList';
import HorizonQueueWidget from './components/HorizonQueueWidget';
import HorizonRedisWidget from './components/HorizonRedisWidget';

export default function extendDashboardPage() {
  extend(DashboardPage.prototype, 'availableWidgets', function (widgets: ItemList<Mithril.Children>) {
    widgets.add('horizon-queue', <HorizonQueueWidget />, 30);
    widgets.add('horizon-redis', <HorizonRedisWidget />, 25);
  });
}
