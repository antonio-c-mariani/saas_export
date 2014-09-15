
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

    Y.on('change', function(e) {
        name = this.get('name');
        value = this.get('value');
        window.location="index.php?action=course_mapping&od_id="+name+"&group_map_id="+value;
    }, '.select_group_map');

    Y.on('change', function(e) {
        value = this.get('value');
        window.location="index.php?action=course_mapping&oc_id="+value;
    }, '.select_oferta_curso');

}
