<?php

$string['pluginname'] = 'Exportar dados SAAS';

$string['add_polo'] = 'Adicionar novo polo';
$string['add_oferta'] = 'Adicionar oferta de disciplina';
$string['nome_polo'] = 'Nome do polo';
$string['invalid_nome_polo'] = 'Nome de polo inválido';
$string['exists_nome_polo'] = 'Já há polo com este nome';
$string['cidade'] = 'Cidade';
$string['invalid_cidade'] = 'Nome de cidade inválido';
$string['estado'] = 'UF';

$string['no_permission_to_config'] = 'Você não tem permissão para configurar o relatório de exportação de dados para SAAS';
$string['no_permission_to_export'] = 'Você não tem permissão para exportar de dados para SAAS';
$string['userid_field_unknown'] = 'Campo identificador de usuário inexistente: \'{$a}\'';

$string['oferta_curso'] = 'Oferta de curso';
$string['disciplina'] = 'Disciplina';
$string['inicio'] = 'Início';
$string['fim'] = 'Fim';
$string['periodo'] = 'Período';

$string['guidelines'] = 'Orientações';
$string['settings'] = 'Configurações';
$string['saas_data'] = 'Dados SAAS';
$string['overview'] = 'Visão geral';
$string['export'] = 'Exportação';
$string['reload'] = 'Atualizar dados';
$string['reloaded'] = 'Os dados de ofertas de curso/disciplinas e de polos foram atualizados a partir do SAAS';

$string['moodle_courses'] = 'Cursos Moodle';
$string['moodle_categories'] = 'Categorias Moodle';
$string['polos'] = 'Polos';
$string['polos_title'] = 'Polos definidos no SAAS';
$string['ofertas'] = 'Ofertas de cursos e disciplinas';
$string['ofertas_title'] = 'Ofertas de cursos e disciplinas definidos no SAAS';

$string['saas_settings'] = 'Configurações do SAAS';
$string['course_settings'] = 'Configurações de cursos Moodle';
$string['user_settings'] = 'Configurações de usuários';
$string['cpf_settings'] = 'Configurações de CPF (opcional)';
$string['role_settings'] = 'Configurações de papeis/funções';

$string['duplicated_role'] = 'Há papeis selecionados em outro perfil de usuário';
$string['duplicated_disciplina'] = 'Disciplina já disponível na oferta de curso selecionada';
$string['no_course_mapping'] = 'Não há cursos Moodle mapeados para ofertas de disciplinas do SAAS';
$string['no_no_ofertas_disciplinas'] = 'Não há ofertas de disciplinas ativas registradas no SAAS';

$string['course_mapping'] = 'Mapeamento de disciplinas';
$string['course_mapping_help'] = 'Indica a forma como os cursos do Moodle são mapeados para disciplinas do SAAS';
$string['one_to_one'] = 'Cada curso Moodle corresponde a uma e somente uma oferta de disciplina do SAAS';
$string['one_to_many'] = 'Um curso Moodle contém conteúdo de mais de uma oferta de disciplina do SAAS';
$string['many_to_one'] = 'O conteúdo de uma oferta de disciplina do SAAS está distribuído em mais de um curso Moodle';

$string['polo_mapping'] = 'Mapeamento de polos';
$string['polo_mapping_help'] = 'Indica a forma como os polos são mapeados na estrutura de cursos do Moodle';
$string['no_polo'] = 'Não há no Moodle forma de agrupar estudantes por polo';
$string['group_to_polo'] = 'São utilizados grupos nos cursos Moodle para agrupar estudantes por polo';
$string['category_to_polo'] = 'Os polos são identificados por categorias no Moodle';
$string['course_to_polo'] = 'Os polos são identificados por cursos no Moodle';
$string['title_no_polo'] = 'A configuração atual indica que não há no Moodle forma de agrupar estudantes por polo';

$string['group_to_polo_msg1'] = 'Estão listados abaixo todos os grupos existentes nos cursos Moodle que foram mapeados na aba \'Mapeamento de disciplinas\'.
   Estes grupos podem, agora, ser mapeados para polos registrados no SAAS, sendo possível mapear mais de um grupo para o mesmo polo.
   Os grupos não mapeados são ignorados pois considera-se que eles não correspondem a polos.';
$string['group_to_polo_msg2'] = 'Os estudantes e tutores pertencentes aos grupos mapeados serão associados aos polos correspondentes no SAAS.
   Por sua vez, os polos serão associados às ofertas de curso em função do mapeamento dos cursos Moodle para as ofertas de disciplinas (ver aba \'Mapeamento de disciplinas\')';

$string['category_to_polo_msg1'] = 'Estão listados abaixo os polos registrados no SAAS. A cada polo podem ser associadas uma ou mais categorias do Moodle,
   fato que indica que nos cursos Moodle que estejam nestas categorias (ou em suas sub-categorias) estão inscritos apenas estudantes e tutores desse polo.';
$string['category_to_polo_msg2'] = 'Os estudantes e tutores inscritos em cursos Moodle que tenham sido mapeados na aba \'Mapeamento de disciplinas\' serão associados aos polos do SAAS
   correspondentes à categoria na qual esteja esse curso Moodle, na oferta de curso do SAAS identificada no mapeamento realizado na aba supra citada.';

$string['course_to_polo_msg1'] = 'Estão listados abaixo os polos registrados no SAAS. A cada polo podem ser associadas um ou mais cursos do Moodle,
   fato que indica que nos cursos mapeados estão inscritos apenas estudantes e tutores desse polo.';
$string['course_to_polo_msg2'] = 'Os estudantes e tutores inscritos em cursos Moodle que tenham sido mapeados na aba \'Mapeamento de disciplinas\' serão associados aos polos do SAAS
   correspondentes aos cursos Moodle, na oferta de curso do SAAS identificada no mapeamento realizado na aba supra citada.';
//------------------------------------------------------------------

$string['student'] = 'Estudante';
$string['students'] = 'Estudantes';
$string['teacher'] = 'Professor';
$string['teachers'] = 'Professores';
$string['tutor_inst'] = 'Tutor a distância';
$string['tutor_insts'] = 'Tutores a distância';
$string['tutor_polo'] = 'Tutor presencial (polo)';
$string['tutor_polos'] = 'Tutores presenciais (polo)';
$string['title'] = 'Exportação de dados para o SAAS';
$string['success'] = 'Todos os dados foram enviados com sucesso.';
$string['config'] = 'Configurações';
$string['no_groups_found'] = 'Não foram encontrados grupos no Moodle para relacionar como polos.';
$string['no_data_to_export'] = 'Não há dados a serem exportados.';

$string['name_field_teacher'] = 'Nome de professores';
$string['name_field_teacher_help'] = 'Campo(s) do perfil de professores que define(m) seu nome completo.';
$string['name_field_student'] = 'Nome de estudantes';
$string['name_field_student_help'] = 'Campo(s) do perfil de estudantes que define(m) seu nome completo.';
$string['name_field_tutor_polo'] = 'Nome de tutores polo';
$string['name_field_tutor_polo_help'] = 'Campo(s) do perfil de tutores polo que define(m) seu nome completo.';
$string['name_field_tutor_inst'] = 'Nome de tutores instituição';
$string['name_field_tutor_inst_help'] = 'Campo(s) do perfil de tutores da instituição (a distância) que define(m) seu nome completo.';

$string['name_regexp'] = 'Filtro para Nome';
$string['name_regexp_help'] = 'Expressão regular aplicada sobre o nome do usuário que possibilita filtrar o valor';

$string['cpf_field_teacher'] = 'CPF de professores';
$string['cpf_field_teacher_help'] = 'Campo do perfil de professores que contém seu CPF.';
$string['cpf_field_student'] = 'CPF de estudantes';
$string['cpf_field_student_help'] = 'Campo do perfil de estudantes que contém seu CPF.';
$string['cpf_field_tutor_polo'] = 'CPF de tutores polo';
$string['cpf_field_tutor_polo_help'] = 'Campo do perfil de tutores polo que contém seu CPF.';
$string['cpf_field_tutor_inst'] = 'CPF de tutores instituição';
$string['cpf_field_tutor_inst_help'] = 'Campo do perfil de tutores da institução (a distância) que contém seu CPF.';

$string['cpf_regexp'] = 'Filtro para CPF';
$string['cpf_regexp_help'] = 'Expressão regular aplicada sobre o valor de CPF que possibilita filtrar o valor';

$string['roles_teacher'] = 'Papeis de professores';
$string['roles_teacher_help'] = 'Papeis com os quais professores são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas. <BR>
                            Utilize <ctrl\> + click para selecionar múltiplos papeis ou para desmarcar uma opção.';
$string['roles_student'] = 'Papeis de estudantes';
$string['roles_student_help'] = 'Papeis com os quais estudantes são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas. <BR>
                            Utilize <ctrl\> + click para selecionar múltiplos papeis ou para desmarcar uma opção.';
$string['roles_tutor_polo'] = 'Papeis de tutores polo';
$string['roles_tutor_polo_help'] = 'Papeis com os quais tutores de polo (presenciais) são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas. <BR>
                            Utilize <ctrl\> + click para selecionar múltiplos papeis ou para desmarcar uma opção.';
$string['roles_tutor_inst'] = 'Papeis de tutores instituição';
$string['roles_tutor_inst_help'] = 'Papeis com os quais tutores da instituição (a distância) são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas. <BR>
                            Utilize <ctrl\> + click para selecionar múltiplos papeis ou para desmarcar uma opção.';


$string['profile_field_name'] = 'Nome:';
$string['profile_field_name_help'] = 'Campo(s) do perfil do usuário que define o nome completo para este papel.';
$string['saas_export:view'] = 'Visualizar configuração, mapeamento e exportações para o SAAS';
$string['saas_export:config'] = 'Configurar exportação de dados para o SAAS';
$string['saas_export:export'] = 'Exportar dados para o SAAS';

$string['ws_url'] = 'URL SAAS';
$string['ws_url_help'] = 'URL do servidor do SAAS para onde os dados serão transferidos.';

$string['api_key'] = 'Chave da Instituição';
$string['api_key_help'] = 'Chave de identificação da instituição definida no SAAS. Entre em contato com a administração do SAAS para solicitar a chave.';

$string['course_name_default'] = 'Nome de cursos';
$string['course_name_default_help'] = 'Campo do curso Moodle correspondente ao nome da oferta de disciplina.';

$string['username'] = 'Id. Usuário';
$string['userid_field'] = 'Id. de usuários';
$string['userid_field_help'] = 'Campo do perfil do usuário a ser usado como identificador único do usuário no SAAS.';
$string['filter_userid_field'] = 'Aplicar filtro de CPF';
$string['filter_userid_field_help'] = 'Aplicar filtro de limpeza e formatação de CFP sobre o campo \'Id. de usuários\', resultando em um número de 11 dígitos (com zeros a esquerda).';

$string['lastcourseaccess'] = 'Último acesso à disciplina';
$string['suspended'] = 'Suspenso';
$string['suspended_as_evaded'] = 'Tratar como evasão as inscrições suspensas';
$string['suspended_as_evaded_help'] = 'Considerar como evasão as inscrições em cursos Moodle que estão com o estado de suspensas. Caso esta opção não esteja marcada,
    as inscrições suspensas são completamente ignoradas.';

$string['cpf'] = 'CPF';
$string['cpf_help'] = 'Campo do perfil do usuário que contém o CPF.';

$string['desc_user_role'] = 'Papéis com os quais {$a} são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas. <BR>
                            Utilize <ctrl\> + click para selecionar múltiplos papeis ou para desmarcar uma opção.';
$string['roles'] = 'Papéis';
$string['roles_help'] = 'Papéis com os quais estes usuários são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas.';

$string['home_title'] = 'Sincronização de dados com o SAAS';
$string['no_course_offer_from_ws'] = 'Não foi localizada nenhuma oferta (edição) de curso cadastrada no SAAS. 
<BR>Verifique no SAAS se há ofertas de curso sujeitas a avaliação e se a chave da instituição foi corretamente configurada.
<p>Entre em contato com a administração do SAAS para mais informações.</p>';
$string['no_class_offer_from_ws'] = 'Não foi localizada nenhuma oferta de disciplina cadastrada no SAAS. 
<BR>Verifique no SAAS se há ofertas de disciplinas sujeitas a avaliação e se a chave da instituição foi corretamente configurada.
<p>Entre em contato com a administração do SAAS para mais informações.</p>';

$string['no_mapped_classes_offer'] = 'Não há ofertas de disciplinas mapeadas.';
$string['no_mapped_courses_offer'] = 'Não há ofertas de cursos mapeadas mapeadas.';
$string['bd_error'] = 'Falha de acesso ao banco de dados: {$a}';
$string['ws_error'] = 'Houve falha na comunicação com o servidor do SAAS.<BR>
    Verifique se os campos \'URL SAAS\' e \'Chave da instituição\' estão corretos na aba de configurações.<BR>
    Entre em contato com a equipe do SAAS caso tenhas alguma dúvida.';
$string['update_data_error'] = 'Erro ao atualizar base de dados {$a}';
$string['get_data_error'] = 'Erro ao acessar base de dados {$a}';

$string['mark_groups'] = 'Marque os grupos abaixo que representam polos da Instituição';
$string['mark_new_groups'] = 'Marque os novos grupos abaixo que representam polos da Instituição';
$string['total_users_to_send'] = 'Total de usuários: ';
$string['users_by_class_offer'] = 'Número de usuários por oferta de disciplina: ';

$string['obs_passo1'] = '<p>Este passo possibilita selecionar uma categoria do Moodle onde estão os cursos Moodle correspondentes às ofertas de disciplina
    de uma oferta (edição) de curso registrada no SAAS. Os cursos Moodle podem estar nesta categoria ou em qualquer de suas sub-categorias.</p>
    <p>Este relacionamento não é obrigatório, mas facilita o mapeamento de cursos Moodle com as ofertas de disciplinas do SAAS (passo 2). 
    Assim, é possível restringir a relação de cursos Moodle apresentados. Se nenhuma categoria for selecionada, serão listados, no passo 2, todos os cursos do
    Moodle como opção de mapeamento.</p> 
    <p>São apresentadas aqui somente as ofertas de curso que tenham sido previamente cadastradas no SAAS.</p>'; 
$string['obs_passo2'] = '<p>Neste passo, é definido o mapeamento entre os cursos Moodle e as ofertas de disciplinas do SAAS. Somente dados de professores,
    estudantes e tutores inscritos nesses cursos mapeados são exportados para o SAAS.</p> 
    <p>São apresentadas abaixo somente as ofertas de disciplinas que tenham sido previamente cadastradas no SAAS e que ainda estejam sujeitas a avaliação.</p>';
$string['obs_passo3'] = '<p>Neste passo, são indicados quais grupos de cursos Moodle (selecionados no passo 2) representam polos da Instituição.
    Este passo, objetiva definir o relacionamento de estudantes e tutores de polo (presencial) por polo. Este relacionamento é exportado para o SAAS.</p>
    <p>Marque os grupos abaixo que representam polos da Instituição.</p>';
$string['obs_passo4'] = '<p>Abaixo é apresentado um quadro resumo com os dados que serão exportados para o SAAS.</p>';

$string['saved'] = 'Os dados foram salvos';
$string['no_changes'] = 'Os dados não foram salvos pois não houve alterações';
$string['moodle_group'] = 'Grupo Moodle';
$string['polo_saas'] = 'Polo SAAS';
