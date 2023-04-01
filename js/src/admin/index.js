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
    .registerSetting({
      setting: 'antoinefr-money.postminimumlength',
      label: app.translator.trans('antoinefr-money.admin.settings.postminimumlength'),
      type: 'number',
    })
    .registerSetting(function () {
      return (
        <div className="Form-group">
          <label>{app.translator.trans('antoinefr-money.admin.settings.moneyfordiscussion')}</label>
          <input type="number" className="FormControl" step="any" bidi={this.setting('antoinefr-money.moneyfordiscussion')} />
        </div>
      );
    })
    .registerSetting(function () {
      return (
        <div className="Form-group">
          <label>{app.translator.trans('antoinefr-money.admin.settings.moneyforlike')}</label>
          <div class="helpText">{app.translator.trans('antoinefr-money.admin.settings.helpextensionlikes')}</div>
          <input type="number" className="FormControl" step="any" bidi={this.setting('antoinefr-money.moneyforlike')} />
        </div>
      );
    })
    .registerSetting({
      setting: 'antoinefr-money.autoremove',
      label: app.translator.trans('antoinefr-money.admin.settings.autoremove'),
      type: 'select',
      options: {
        '0': app.translator.trans('antoinefr-money.admin.autoremove.0'),
        '1': app.translator.trans('antoinefr-money.admin.autoremove.1'),
        '2': app.translator.trans('antoinefr-money.admin.autoremove.2'),
      },
      default: '1',
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
