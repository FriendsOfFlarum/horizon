/// <reference types="mithril" />
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
export default class SettingsPage extends ExtensionPage {
    static register(): void;
    content(): JSX.Element;
}
