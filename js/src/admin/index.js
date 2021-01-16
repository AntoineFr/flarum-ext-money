import {extend, override} from 'flarum/extend';

app.initializers.add('antoinefr-money', () => {
  app.extensionData
    .for('antoinefr-money')
    .registerSetting({
      setting: 'antoinefr-money.moneyname',
      label: app.translator.trans('antoinefr-money.admin.settings.moneyname'),
      type: 'text',
    })
    .registerSetting(function () {
      return (
        <div className="Form-group">
          <label>{app.translator.trans('antoinefr-money.admin.settings.moneyforpost')}</label>
          <input type="number" className="FormControl" step="any" bidi={this.setting('antoinefr-money.moneyforpost')} />
        </div>
      );
    })
    .registerSetting(function () {
      return (
        <div className="Form-group">
          <label>{app.translator.trans('antoinefr-money.admin.settings.moneyfordiscussion')}</label>
          <input type="number" className="FormControl" step="any" bidi={this.setting('antoinefr-money.moneyfordiscussion')} />
        </div>
      );
    })
    .registerSetting({
      setting: 'antoinefr-money.postminimumlength',
      label: app.translator.trans('antoinefr-money.admin.settings.postminimumlength'),
      type: 'number',
    })
    .registerSetting({
      setting: 'antoinefr-money.noshowzero',
      label: app.translator.trans('antoinefr-money.admin.settings.noshowzero'),
      type: 'checkbox',
    })
    .registerPermission(
      {
        icon: 'fas fa-money-bill',
        label: app.translator.trans('antoinefr-money.admin.permissions.edit_money_label'),
        permission: 'user.edit_money',
      }, 
      'moderate',
    );
});
