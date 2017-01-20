import { extend } from 'flarum/extend';
import UserCard from 'flarum/components/UserCard';

app.initializers.add('antoinefr-money', function() {
  extend(UserCard.prototype, 'infoItems', function(items) {
    items.add('money', [
      this.props.user.data.attributes['antoinefr-money.money'],
      app.forum.data.attributes['antoinefr-money.moneyname']
    ]);
  });
});
