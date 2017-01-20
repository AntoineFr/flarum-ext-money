System.register('antoinefr/money/main', ['flarum/extend', 'flarum/components/UserCard', 'flarum/helpers/icon'], function (_export) {
  'use strict';

  var extend, UserCard, icon;
  return {
    setters: [function (_flarumExtend) {
      extend = _flarumExtend.extend;
    }, function (_flarumComponentsUserCard) {
      UserCard = _flarumComponentsUserCard['default'];
    }, function (_flarumHelpersIcon) {
      icon = _flarumHelpersIcon['default'];
    }],
    execute: function () {

      app.initializers.add('antoinefr-money', function () {
        extend(UserCard.prototype, 'infoItems', function (items) {
          items.add('money', [this.props.user.data.attributes['antoinefr-money.money'], app.forum.data.attributes['antoinefr-money.moneyname']]);
        });
      });
    }
  };
});