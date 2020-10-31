import { extend } from 'flarum/extend';
import MoneySettingsModal from './components/MoneySettingsModal';
import PermissionGrid from 'flarum/components/PermissionGrid';

app.initializers.add('antoinefr-money', () => {
  app.extensionSettings['antoinefr-money'] = () => {
    app.modal.show(MoneySettingsModal);
  }
  
  extend(PermissionGrid.prototype, 'moderateItems', (items) => {
    items.add('editMoney', {
      icon: 'fas fa-money-bill',
      label: app.translator.trans('antoinefr-money.admin.permissions.edit_money_label'),
      permission: 'user.edit_money'
    });
  });
});
