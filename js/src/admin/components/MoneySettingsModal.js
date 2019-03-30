import SettingsModal from 'flarum/components/SettingsModal';

export default class MoneySettingsModal extends SettingsModal {
  className() {
    return 'Modal--small';
  }

  title() {
    return app.translator.trans('antoinefr-money.admin.settings.title');
  }

  form() {
    return [
      <div className="Form-group">
        <label for="antoinefr-money.moneyname">{app.translator.trans('antoinefr-money.admin.settings.moneyname')}</label>
        <input required className="FormControl" type="text" id="antoinefr-money.moneyname" bidi={this.setting('antoinefr-money.moneyname')}></input>
        <label for="antoinefr-money.moneyforpost">{app.translator.trans('antoinefr-money.admin.settings.moneyforpost')}</label>
        <input required className="FormControl" type="number" id="antoinefr-money.moneyforpost" step="any" bidi={this.setting('antoinefr-money.moneyforpost')}></input>
        <label for="antoinefr-money.moneyfordiscussion">{app.translator.trans('antoinefr-money.admin.settings.moneyfordiscussion')}</label>
        <input required className="FormControl" type="number" id="antoinefr-money.moneyfordiscussion" step="any" bidi={this.setting('antoinefr-money.moneyfordiscussion')}></input>
        <label for="antoinefr-money.postminimumlength">{app.translator.trans('antoinefr-money.admin.settings.postminimumlength')}</label>
        <input required className="FormControl" type="number" id="antoinefr-money.postminimumlength" step="any" bidi={this.setting('antoinefr-money.postminimumlength')}></input>
        <label for="antoinefr-money.noshowzero">{app.translator.trans('antoinefr-money.admin.settings.noshowzero')}</label>
        <input type="checkbox" step="any" id="antoinefr-money.noshowzero" bidi={this.setting('antoinefr-money.noshowzero')}></input>
      </div>
    ];
  }
}
