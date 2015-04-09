
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
            this.set('disabled', !checked);
            this.set('checked', false);
        });

        Y.all('.polo_'+id).each(function() {
            this.set('disabled', !checked);
            this.set('checked', false);
        });
    }, '.oc_checkbox');

    Y.on('change', function(e) {
        odid = this.get('name');
        group_map_id = this.get('value');
        window.location="index.php?action=course_mapping&subaction=change_group&odid="+odid+"&group_map_id="+group_map_id;
    }, '.select_group_map');

    Y.on('click', function(e) {
        courseid = this.getAttribute('courseid');
        group_map_id = this.getAttribute('group_map_id');
        ocid = this.getAttribute('ocid');
        window.location="index.php?action=course_mapping&subaction=delete&courseid="+courseid+"&group_map_id="+group_map_id+"&ocid="+ocid;
    }, '.delete_map_bt');

    Y.on('click', function(e) {
        group_map_id = this.get('id');
        window.location="index.php?action=course_mapping&subaction=show_tree&group_map_id="+group_map_id;
    }, '.add_map_bt');

    Y.on('change', function(e) {
        ocid = this.get('value');
        var params = { ocid : ocid };

        Y.io('disciplinas_by_oferta_curso.php', {
            //The needed paramaters
            data: build_querystring(params),

            timeout: 5000, //5 seconds for timeout I think it is enough.

            //Define the events.
            on: {

                start : function(transactionid) {
                    var sb = document.getElementById("id_disciplina_id");
                    sb.options.length = 0;
                    var opt = document.createElement('option');
                    opt.value = 0;
                    opt.innerHTML = '-- selecione uma disciplina';
                    sb.appendChild(opt);
                },

                success : function(transactionid, xhr) {
                    var response = xhr.responseText;
                    var disciplinas = Y.JSON.parse(response);
                    var sb = document.getElementById("id_disciplina_id");
                    for(var i = 0; i < disciplinas.length; i++) {
                        var d = disciplinas[i];
                        var opt = document.createElement('option');
                        opt.value = d[0];
                        opt.innerHTML = d[1];
                        sb.appendChild(opt);
                    }
                    sb.options[0].selected="true";

                },
                failure : function(transactionid, xhr) {
                    var msg = {
                        name : 'Falha na obtenção das disciplinas',
                        message : 'Excedido o tempo de conexão com servidor do SAAS'
                    };
                    return new M.core.exception(msg);
                }
            },
            context:this
        });

    }, '#id_oferta_curso_id');

}
