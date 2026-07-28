import { extend } from 'flarum/common/extend';
import type Mithril from 'mithril';

import DashboardPage from 'flarum/admin/components/DashboardPage';
import ItemList from 'flarum/common/utils/ItemList';
import HorizonQueueWidget from './components/HorizonQueueWidget';
import HorizonRedisWidget from './components/HorizonRedisWidget';

export default function extendDashboardPage() {
  extend(DashboardPage.prototype, 'availableWidgets', function (widgets: ItemList<Mithril.Children>) {
    // Replace core's generic queue widget in place with the Horizon-enriched
    // subclass — same slot and priority, so it reads the shared /queue/stats
    // endpoint (backed by HorizonQueueStatsProvider) and adds horizon tiles.
    // One unified queue card rather than core's plus a parallel horizon one.
    if (widgets.has('queue')) {
      widgets.setContent('queue', <HorizonQueueWidget />);
    } else {
      // Defensive: if core didn't register it (e.g. an older core), add ours.
      widgets.add('queue', <HorizonQueueWidget />, 15);
    }

    // The Redis server card is a distinct concern (memory / ops / eviction),
    // not a queue duplicate, so it remains its own widget.
    widgets.add('horizon-redis', <HorizonRedisWidget />, 12);
  });
}
