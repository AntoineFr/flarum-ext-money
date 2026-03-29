import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

export default [
  new Extend.Admin()
    .setting(
      () => ({
        setting: 'antoinefr-money.moneyname',
        label: app.translator.trans('antoinefr-money.admin.settings.moneyname', {}, true),
        help: app.translator.trans('antoinefr-money.admin.settings.helpmoneyname', {}, true),
        type: 'text',
      })
    )
    .setting(
      () => ({
        setting: 'antoinefr-money.moneyforpost',
        label: app.translator.trans('antoinefr-money.admin.settings.moneyforpost', {}, true),
        type: 'number',
        step: 'any',
      })
    )
    .setting(
      () => ({
        setting: 'antoinefr-money.postminimumlength',
        label: app.translator.trans('antoinefr-money.admin.settings.postminimumlength', {}, true),
        help: app.translator.trans('antoinefr-money.admin.settings.helppostminimumlength', {}, true),
        type: 'number',
      })
    )
    .setting(
      () => ({
        setting: 'antoinefr-money.moneyfordiscussion',
        label: app.translator.trans('antoinefr-money.admin.settings.moneyfordiscussion', {}, true),
        type: 'number',
        step: 'any',
      })
    )
    .setting(
      () => ({
        setting: 'antoinefr-money.moneyforlike',
        label: app.translator.trans('antoinefr-money.admin.settings.moneyforlike', {}, true),
        help: app.translator.trans('antoinefr-money.admin.settings.helpextensionlikes', {}, true),
        type: 'number',
        step: 'any',
      })
    )
    .setting(
      () => ({
        setting: 'antoinefr-money.autoremove',
        label: app.translator.trans('antoinefr-money.admin.settings.autoremove', {}, true),
        type: 'select',
        options: {
          '0': app.translator.trans('antoinefr-money.admin.autoremove.0', {}, true),
          '1': app.translator.trans('antoinefr-money.admin.autoremove.1', {}, true),
          '2': app.translator.trans('antoinefr-money.admin.autoremove.2', {}, true),
        },
        default: '1',
      })
    )
    .setting(
      () => ({
        setting: 'antoinefr-money.cascaderemove',
        label: app.translator.trans('antoinefr-money.admin.settings.cascaderemove', {}, true),
        type: 'checkbox',
      })
    )
    .setting(
      () => ({
        setting: 'antoinefr-money.ignorenotifyingusers',
        label: app.translator.trans('antoinefr-money.admin.settings.ignore_notifying_users', {}, true),
        type: 'checkbox',
      })
    )
    .setting(
      () => ({
        setting: 'antoinefr-money.noshowzero',
        label: app.translator.trans('antoinefr-money.admin.settings.noshowzero', {}, true),
        type: 'checkbox',
      })
    )
    .permission(
      () => ({
        icon: 'fas fa-money-bill',
        label: app.translator.trans('antoinefr-money.admin.permissions.edit_money_label', {}, true),
        permission: 'user.edit_money',
      }),
      'moderate',
    )
    .permission(
      () => ({
        icon: 'far fa-eye',
        label: app.translator.trans('antoinefr-money.admin.permissions.disable_money_label', {}, true),
        permission: 'discussion.money.disable_money',
      }),
      'start',
    )
];
