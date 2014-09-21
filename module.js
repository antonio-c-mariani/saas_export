
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
        checked = this.get('checked');

        Y.all('.od_'+id).each(function() {
            this.set('disabled', checked);
            this.set('checked', checked);
        });

        Y.all('.polo_'+id).each(function() {
            this.set('disabled', checked);
            this.set('checked', checked);
        });

        Y.all('#od_'+id).each(function() {
            this.set('disabled', checked);
            this.set('checked', checked);
        });

        Y.all('#polo_'+id).each(function() {
            this.set('disabled', checked);
            this.set('checked', checked);
        });
    }, '.oc_checkbox');

    Y.on('change', function(e) {
        odid = this.get('name');
        group_map_id = this.get('value');
        window.location="index.php?action=course_mapping&subaction=map&odid="+odid+"&group_map_id="+group_map_id;
    }, '.select_group_map');

    Y.on('click', function(e) {
        courseid = this.getAttribute('courseid');
        group_map_id = this.getAttribute('group_map_id');
        ocid = this.getAttribute('ocid');
        window.location="index.php?action=course_mapping&&subaction=delete&courseid="+courseid+"&group_map_id="+group_map_id+"&ocid="+ocid;
    }, '.delete_bt');

}
