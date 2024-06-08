app.initializers.add('antoinefr-money', () => {
  app.extensionData
    .for('antoinefr-money')
    .registerSetting({
      setting: 'antoinefr-money.moneyname',
      label: app.translator.trans('antoinefr-money.admin.settings.moneyname'),
      help: app.translator.trans('antoinefr-money.admin.settings.helpmoneyname'),
      type: 'text',
    })
    .registerSetting({
      setting: 'antoinefr-money.moneyforpost',
      label: app.translator.trans('antoinefr-money.admin.settings.moneyforpost'),
      type: 'number',
    })
    .registerSetting({
      setting: 'antoinefr-money.postminimumlength',
      label: app.translator.trans('antoinefr-money.admin.settings.postminimumlength'),
      help: app.translator.trans('antoinefr-money.admin.settings.helppostminimumlength'),
      type: 'number',
    })
    .registerSetting({
      setting: 'antoinefr-money.moneyfordiscussion',
      label: app.translator.trans('antoinefr-money.admin.settings.moneyfordiscussion'),
      type: 'number',
    })
    .registerSetting({
      setting: 'antoinefr-money.moneyforlike',
      label: app.translator.trans('antoinefr-money.admin.settings.moneyforlike'),
      help: app.translator.trans('antoinefr-money.admin.settings.helpextensionlikes'),
      type: 'number',
    })
    .registerSetting({
      setting: 'antoinefr-money.moneyifprivate',
      label: app.translator.trans('antoinefr-money.admin.settings.moneyifprivate'),
      help: app.translator.trans('antoinefr-money.admin.settings.helpextensionbyobu'),
      type: 'checkbox',
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
      setting: 'antoinefr-money.cascaderemove',
      label: app.translator.trans('antoinefr-money.admin.settings.cascaderemove'),
      type: 'checkbox',
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
