import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserCard from 'flarum/forum/components/UserCard';
import UserControls from 'flarum/forum/utils/UserControls';
import Button from 'flarum/common/components/Button';
import UserMoneyModal from './components/UserMoneyModal';
import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';

app.initializers.add('antoinefr-money', () => {
  User.prototype.canEditMoney = Model.attribute('canEditMoney');

  extend(UserCard.prototype, 'infoItems', function (items) {
    const moneyName = app.forum.attribute('antoinefr-money.moneyname') || '[money]';

    if (app.forum.attribute('antoinefr-money.noshowzero')) {
      if (this.attrs.user.data.attributes.money !== 0) {
        items.add('money', <span>{moneyName.replace('[money]', this.attrs.user.data.attributes['money'])}</span>);
      }
    } else {
      items.add('money', <span>{moneyName.replace('[money]', this.attrs.user.data.attributes['money'])}</span>);
    }
  });

  extend(UserControls, 'moderationControls', (items, user) => {
    if (user.canEditMoney()) {
      items.add(
        'money',
        Button.component(
          {
            icon: 'fas fa-money-bill',
            onclick: () => app.modal.show(UserMoneyModal, { user }),
          },
          app.translator.trans('antoinefr-money.forum.user_controls.money_button')
        )
      );
    }
  });
});
