import { extend } from 'flarum/extend';
import MoneySettingsModal from 'antoinefr/money/components/MoneySettingsModal';

app.initializers.add('antoinefr-money', function() {
  app.extensionSettings['antoinefr-money'] = function() {
    app.modal.show(new MoneySettingsModal());
  }
});