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
$string['no_roles'] = 'Não foi selecionado nenhum papel para professores, estudantes e tutores. Nesta condição, nenhum dado será exportado para o SAAS.';

$string['oferta_curso'] = 'Oferta de curso SAAS';
$string['disciplina'] = 'Disciplina';
$string['inicio'] = 'Início';
$string['fim'] = 'Fim';
$string['periodo'] = 'Período';

$string['od_mapped'] = 'Ofertas de disciplinas mapeadas:';
$string['od_notmapped'] = 'Ofertas de disciplinas não mapeadas, mas com dados no SAAS:';
$string['polos_mapped'] = 'Polos mapeadas:';
$string['polos_notmapped'] = 'Polos não mapeados, mas com dados no SAAS:';
$string['mark_clear'] = 'Marque para limpar os dados no SAAS';

$string['course_selection'] = 'Seleção de curso Moodle';
$string['course_selection_help'] = '<P>Clique num dos cursos Moodle abaixo para associá-lo à oferta de disciplina indicada
    nesta página.</P>
    <P>Para facilitar o processo, de início são apresentadas algumas opções de cursos cujos nomes mais se
    assemelham à da oferta de disciplina, baseado na Distância Levenshtein. Caso o curso Moodle não esteja nesta lista,
    outra opção é navegar pela hierarquia de classes até localizar o curso de interesse. Nesta hierarquia
    são apresentados apenas os cursos que ainda não foram associados a outra oferta de disciplina.</P>';

$string['guidelines'] = 'Orientações';
$string['settings'] = 'Configurações';
$string['saas_data'] = 'Dados SAAS';
$string['overview'] = 'Visão geral';
$string['export'] = 'Exportação';
$string['reload'] = 'Atualizar dados';
$string['reloaded'] = 'Os dados de ofertas de curso/disciplinas e de polos foram atualizados a partir do SAAS';

$string['export_help'] = '<P>Selecione abaixo as ofertas de curso, de disciplinas e polos cujos dados devam ser exportados para o SAAS.<P>
    <P>Ao selecionar uma oferta de curso, serão exportados dados de todas as ofertas de disciplinas e polos associados a esta oferta de curso.
    Para ter um controle mais fino na exportação é necessário desmarcar a caixa correspondente à oferta de curso.  Neste caso são liberadas
    as caixas de seleção de ofertas de disciplina e polos correspondentes à oferta de curso, passando a ser exportados apenas os dados
    correspondentes aos itens selecionados.</P>';

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
$string['role_settings'] = 'Configurações de papéis/funções';

$string['duplicated_role'] = 'Há papel já selecionado em outro perfil de usuário';
$string['duplicated_disciplina'] = 'Disciplina indisponível para oferta de curso selecionada';
$string['no_course_mapping'] = 'Não há cursos Moodle mapeados para ofertas de disciplinas do SAAS';
$string['no_ofertas_disciplinas'] = 'Não há ofertas de disciplinas ativas registradas no SAAS';
$string['no_ofertas_cursos'] = 'Não há ofertas de cursos ativas registradas no SAAS';
$string['no_polos'] = 'Não há polo ativo registrado no SAAS';

$string['course_mapping'] = 'Mapeamento de disciplinas';
$string['course_mapping_help'] = 'Indica a forma como os cursos do Moodle são mapeados para disciplinas do SAAS';
$string['one_to_one'] = 'Um curso Moodle para cada oferta de disciplina do SAAS';
$string['one_to_many'] = 'Um curso Moodle para mais de uma oferta de disciplina do SAAS';
$string['many_to_one'] = 'Mais de um curso Moodle para uma oferta de disciplina do SAAS';

$string['one_to_one_help'] = '<P>Abaixo aparecem listadas à esquerda as ofertas de disciplinas registradas no SAAS e à direita os cursos Moodle que correspondentes a
    essas ofertas de disciplinas. Dado que a configuração atual deste módulo indica que cada curso Moodle corresponde a uma única oferta de disciplina,
    é possível adicionar um único curso Moodle à direita para cada oferta de disciplina.</P>
    <P>Somente as ofertas de disciplinas que tiverem sido associadas a algum cursos Moodle poderão ser exportadas posteriormente.</P>';
$string['one_to_many_help'] = '<P>Abaixo aparecem listadas à esquerda as ofertas de disciplinas registradas no SAAS e à direita os cursos Moodle que correspondentes a
    essas ofertas de disciplinas. Dado que a configuração atual deste módulo indica que um curso Moodle pode estar associado a mais de oferta de disciplina,
    é possível agrupar mais de um oferta de disciplina para um mesmo curso Moodle. Para tal, observe a existência da coluna denominada de \'Mover para\'.
    Ao ser selecionado um grupo numa de suas caixas de seleção, a oferta de disciplina correspondente será movida para o grupo selecionado.</P>
    <P>A operação de movimentação de uma oferta de disciplina entre grupos não é possível no caso do grupo ser composto por uma única oferta de disciplina e
    este grupo já estar associado a um curso Moodle. Neste caso é necessário remover primeiramente o curso Moodle.</P>
    <P>Somente as ofertas de disciplinas que tiverem sido associadas a algum cursos Moodle poderão ser exportadas posteriormente.</P>';
$string['many_to_one_help'] = '<P>Abaixo aparecem listadas à esquerda as ofertas de disciplinas registradas no SAAS e à direita os cursos Moodle que correspondentes a
    essas ofertas de disciplinas. Dado que a configuração atual deste módulo indica que pode haver mais de um curso Moodle para uma mesma
    oferta de disciplina, é possível adicionar mais de um curso Moodle para cada oferta de disciplina.</P>
    <P>Somente as ofertas de disciplinas que tiverem sido associadas a algum cursos Moodle poderão ser exportadas posteriormente.</P>';

$string['title_no_polo'] = 'A configuração atual indica que não há no Moodle forma de agrupar estudantes por polo';
$string['polo_mapping'] = 'Mapeamento de polos';
$string['polo_mapping_help'] = 'Indica a forma como os polos são mapeados na estrutura de cursos do Moodle';
$string['no_polo'] = 'Não há no Moodle forma de agrupar estudantes por polo';
$string['group_to_polo'] = 'São utilizados grupos nos cursos Moodle para agrupar estudantes por polo';
$string['category_to_polo'] = 'Os polos são identificados por categorias no Moodle';
$string['course_to_polo'] = 'Os polos são identificados por cursos no Moodle';

$string['group_to_polo_help'] = '<P>Estão listados abaixo (à esquerda) os grupos existentes nos cursos Moodle que foram mapeados
    na aba \'Mapeamento de disciplinas\'. A cada grupo pode, agora, ser associado um polo do SAAS, fato que indica que neste grupo
    estão inscritos apenas estudantes e tutores desse polo.</P>
    <P>Este mapeamento define a relação de estudantes e tutores com seus respectivos polos a serem
    exportados para o SAAS, conforme descrito no \'Cenário A\' da aba de orientações.</P>
    <p>Há alguns grupos Moodle que podem não ter sido ainda mapeados. Estes grupos aparecem em <span style="color:red">vermelho</span> abaixo,
    sendo que à direita (Polo SAAS) aparece uma sugestão de mapeamento em função de haver um nome de polo exatamente com o mesmo nome do grupo.
    Caso não haja tal polo, é apresentada a opção que indica que este grupo não corresponde a polo do SAAS.</P>';

$string['category_to_polo_help'] = '<P>É apresentada abaixo (à esquerda) a hierarquia de categorias do Moodle para as quais
    há algum curso que tenha sido mapeado na aba \'Mapeamento de disciplinas\'.
    A cada categoria (uma por ramo da hierarquia) pode ser associado um polo do SAAS, fato que indica que nos cursos Moodle
    que estejam nesta categoria (ou em suas sub-categorias) estão inscritos apenas estudantes e tutores desse polo.</P>
    <P>Este mapeamento define a relação de estudantes e tutores com seus respectivos polos a serem
    exportados para o SAAS, conforme descrito no \'Cenário B\' da aba de orientações.</P>';

$string['course_to_polo_help'] = '<P>É apresentada abaixo (à esquerda) a hierarquia de categorias e cursos do Moodle para as quais
    haja algum curso que tenha sido mapeado na aba \'Mapeamento de disciplinas\'.
    A cada um dos curso listados pode ser associado um polo do SAAS, fato que indica que neste curso Moodle estão inscritos apenas
    estudantes e tutores desse polo.</P>
    <p>Este mapeamento define a relação de estudantes e tutores com seus respectivos polos a serem
    exportados para o SAAS, conforme descrito no \'Cenário C\' da aba de orientações.</P>';

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
$string['export_ok'] = 'Os dados foram exportados para o SAAS. <BR>
    Visite o <A HREF="{$a}" TARGET="_new">relatório de exportação do SAAS</A> para ver detalhes.';
$string['export_errors'] = 'Houve {$a->errors} falha(s) ao exportar os dados para o SAAS.<BR>
    Visite o <A HREF="{$a->report_url}" TARGET="_new">relatório de exportação do SAAS</A> para ver detalhes.';

$string['name_field_teacher'] = 'Nome de professores';
$string['name_field_teacher_help'] = 'Campo(s) do perfil de professores que define(m) seu nome completo.';
$string['name_field_student'] = 'Nome de estudantes';
$string['name_field_student_help'] = 'Campo(s) do perfil de estudantes que define(m) seu nome completo.';
$string['name_field_tutor_polo'] = 'Nome de tutores polo';
$string['name_field_tutor_polo_help'] = 'Campo(s) do perfil de tutores polo que define(m) seu nome completo.';
$string['name_field_tutor_inst'] = 'Nome de tutores a distância';
$string['name_field_tutor_inst_help'] = 'Campo(s) do perfil de tutores a distância (da instituição) que define(m) seu nome completo.';

$string['name_regexp'] = 'Filtro para Nome';
$string['name_regexp_help'] = 'Expressão regular aplicada sobre o nome do usuário que possibilita filtrar o valor';

$string['cpf_field_teacher'] = 'CPF de professores';
$string['cpf_field_teacher_help'] = 'Campo do perfil de professores que contém seu CPF.';
$string['cpf_field_student'] = 'CPF de estudantes';
$string['cpf_field_student_help'] = 'Campo do perfil de estudantes que contém seu CPF.';
$string['cpf_field_tutor_polo'] = 'CPF de tutores polo';
$string['cpf_field_tutor_polo_help'] = 'Campo do perfil de tutores polo que contém seu CPF.';
$string['cpf_field_tutor_inst'] = 'CPF de tutores a distância';
$string['cpf_field_tutor_inst_help'] = 'Campo do perfil de tutores a distância (da institução) que contém seu CPF.';

$string['cpf_regexp'] = 'Filtro para CPF';
$string['cpf_regexp_help'] = 'Expressão regular aplicada sobre o valor de CPF que possibilita filtrar o valor';

$string['roles_teacher'] = 'Papéis de professores';
$string['roles_teacher_help'] = 'Selecione os papéis com os quais professores são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas. <BR>
   Utilize <ctrl\> + clique para selecionar múltiplos papéis. Caso nenhum papel seja selecionado, os dados de professores não serão exportados para o SAAS.';
$string['roles_student'] = 'Papéis de estudantes';
$string['roles_student_help'] = 'Selecione os papéis com os quais estudantes são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas. <BR>
   Utilize <ctrl\> + clique para selecionar múltiplos papéis. Caso nenhum papel seja selecionado, os dados de estudantes não serão exportados para o SAAS.';
$string['roles_tutor_polo'] = 'Papéis de tutores presenciais';
$string['roles_tutor_polo_help'] = 'Selecione os papéis com os quais tutores presenciais (de polo) são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas. <BR>
   Utilize <ctrl\> + clique para selecionar múltiplos papéis. Caso nenhum papel seja selecionado, os dados de tutores presenciais não serão exportados para o SAAS.';
$string['roles_tutor_inst'] = 'Papéis de tutores a distância';
$string['roles_tutor_inst_help'] = 'Selecione papéis com os quais tutores a distância (da instituição) são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas. <BR>
   Utilize <ctrl\> + clique para selecionar múltiplos papéis. Caso nenhum papel seja selecionado, os dados de tutores a distância não serão exportados para o SAAS.';

$string['profile_field_name'] = 'Nome:';
$string['profile_field_name_help'] = 'Campo(s) do perfil do usuário que define o nome completo para este papel.';
$string['saas_export:view'] = 'Visualizar configuração, mapeamento e exportações para o SAAS';
$string['saas_export:config'] = 'Configurar exportação de dados para o SAAS';
$string['saas_export:export'] = 'Exportar dados para o SAAS';

$string['ws_url'] = 'URL SAAS';
$string['ws_url_help'] = 'URL do servidor do SAAS para onde os dados serão transferidos.';

$string['api_key'] = 'Chave da Instituição';
$string['api_key_help'] = 'Chave de identificação da instituição definida no SAAS. Entre em contato com a administração do SAAS para solicitar a chave.';
$string['saas_access_fail'] = 'Não foi possível obter os dados da institução via URL: \'{$a->url_saas}\'.<br>
    Entre em contato com a administração do SAAS para confirmar a validade desta URL.<br>
    Erro: \'{$a->message}\'';
$string['nome_instituicao'] = 'Instituição';

$string['course_name_default'] = 'Nome de cursos';
$string['course_name_default_help'] = 'Campo do curso Moodle correspondente ao nome da oferta de disciplina.';

$string['username'] = 'Id. Usuário';
$string['userid_field'] = 'Id. de usuários';
$string['userid_field_help'] = 'Campo do perfil do usuário a ser usado como identificador único do usuário no SAAS.';
$string['filter_userid_field'] = 'Aplicar filtro de CPF';
$string['filter_userid_field_help'] = 'Aplicar filtro de limpeza e formatação de CPF sobre o campo \'Id. de usuários\', resultando em um número de 11 dígitos (com zeros a esquerda).';

$string['lastcourseaccess'] = 'Último acesso<br>à disciplina';
$string['suspended'] = 'Evasão<br><small>Suspenso disciplina</small>';
$string['global_suspended'] = 'Evasão<br><small>Suspenso global</small>';
$string['suspended_as_evaded'] = 'Tratar como evasão as inscrições suspensas';
$string['suspended_as_evaded_help'] = 'Considerar como evasão as inscrições em cursos Moodle que estão com o estado de suspensas. Caso esta opção não esteja marcada,
    as inscrições suspensas são completamente ignoradas.';

$string['cpf'] = 'CPF';
$string['cpf_help'] = 'Campo do perfil do usuário que contém o CPF.';

$string['desc_user_role'] = 'Papéis com os quais {$a} são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas. <BR>
                            Utilize <ctrl\> + click para selecionar múltiplos papéis ou para desmarcar uma opção.';
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
$string['ws_error'] = '{$a}<BR>Verifique se os campos \'URL SAAS\' e \'Chave da instituição\' estão corretos na aba de configurações.<BR>
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
$string['suspended_settings'] = 'Inscrições suspensas';
