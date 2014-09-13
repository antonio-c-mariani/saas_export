
M.report_saas_export = {};

M.report_saas_export.init = function(Y) {

    Y.on('click', function(e) {
        id = this.get('id');
        checked = this.get('checked');
        Y.all('.'+id).each(function() {
            this.set('checked', checked);
        });
    }, '.checkall_button');

    Y.on('click', function(e) {
        id = this.get('id');
        disabled = this.get('checked');

        Y.all('.od_'+id).each(function() {
            this.set('disabled', disabled);
        });

        Y.all('.polo_'+id).each(function() {
            this.set('disabled', disabled);
        });

        Y.all('#od_'+id).each(function() {
            this.set('disabled', disabled);
        });

        Y.all('#polo_'+id).each(function() {
            this.set('disabled', disabled);
        });
    }, '.oc_checkbox');
}
