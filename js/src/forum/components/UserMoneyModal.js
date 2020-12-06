import Modal from 'flarum/components/Modal';
import Button from 'flarum/components/Button';
import Stream from 'flarum/utils/Stream';

export default class UserMoneyModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);

    this.money = Stream(this.attrs.user.data.attributes['money'] || 0.0);
  }

  className() {
    return 'UserMoneyModal Modal--small';
  }

  title() {
    return app.translator.trans('antoinefr-money.forum.modal.title', {user: this.attrs.user});
  }

  content() {
    const moneyName = app.forum.attribute('antoinefr-money.moneyname') || '[money]';

    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label>{app.translator.trans('antoinefr-money.forum.modal.current')} {moneyName.replace('[money]', this.attrs.user.data.attributes['money'])}</label>
            <input required className="FormControl" type="number" step="any" bidi={this.money} />
          </div>
          <div className="Form-group">
            {Button.component(
              {
                className: 'Button Button--primary',
                type: 'submit',
                loading: this.loading,
              },
              app.translator.trans('antoinefr-money.forum.modal.submit_button')
            )}
          </div>
        </div>
      </div>
    );
  }

  onsubmit(e) {
    e.preventDefault();

    this.loading = true;

    this.attrs.user
    .save({money: this.money()}, { errorHandler: this.onerror.bind(this) })
    .then(this.hide.bind(this))
    .catch(() => {
      this.loading = false;
      m.redraw();
    });
  }
}
