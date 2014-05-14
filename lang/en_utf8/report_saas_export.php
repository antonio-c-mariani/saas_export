<?php

$string['pluginname'] = 'Relatório SAAS';
$string['student'] = 'Aluno';
$string['teacher'] = 'Professor';
$string['tutor'] = 'Tutor a distância';
$string['tutor_polo'] = 'Tutor presencial (polo)';
$string['title'] = 'Exportação de dados para o SAAS';
$string['success'] = 'Todos os dados foram enviados com sucesso.';
$string['config'] = 'Configurações';
$string['no_groups_found'] = 'Não foram encontrados grupos no Moodle para relacionar como polos.';
$string['no_data_to_export'] = 'Não há dados a serem exportados.';

$string['name_field'] = 'Nome de {$a}';
$string['desc_name_field'] = 'Campo(s) do perfil do usuário que define o nome completo no caso de {$a}.';
$string['profile_field_name'] = 'Nome:';
$string['profile_field_name_help'] = 'Campo(s) do perfil do usuário que define o nome completo para este papel.';
$string['saas_export:view'] = 'Exportar dados para o SAAS.';

$string['ws_url'] = 'URL SAAS';
$string['desc_ws_url'] = 'URL do servidor do SAAS para onde os dados serão transferidos.';
$string['ws_url_help'] = 'URL do servidor do SAAS para onde os dados serão transferidos.';

$string['api_key'] = 'Chave da Instituição';
$string['desc_api_key'] = 'Chave de identificação da instituição definida no SAAS. Entre em contato com a administração do SAAS para solicitar a chave.'; 

$string['course_name_default'] = 'Nome do Curso Moodle';
$string['desc_course_name_default'] = 'Campo do curso Moodle correspondente ao nome da oferta de disciplina.';
$string['course_name_default_help'] = 'Campo do curso Moodle correspondente ao nome da oferta de disciplina.';

$string['user_id_field'] = 'Identificador do usuário';
$string['desc_user_id_field'] = 'Campo do perfil do usuário a ser usado como identificador único do usuário no SAAS.';
$string['user_id_field_help'] = 'Campo do perfil do usuário a ser usado como identificador único do usuário no SAAS.';

$string['cpf_field'] = 'CPF de {$a}';
$string['desc_cpf_field'] = 'Campo do perfil do usuário que contém o CPF para o caso de {$a}.';
$string['cpf'] = 'CPF';
$string['cpf_help'] = 'Campo do perfil do usuário que contém o CPF.';

$string['user_role'] = 'Papéis de {$a}';
$string['desc_user_role'] = 'Papéis com os quais {$a} são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas. <BR>
                            Utilize <ctrl\> + click para selecionar múltiplos papeis ou para desmarcar uma opção.';
$string['roles'] = 'Papéis';
$string['roles_help'] = 'Papéis com os quais estes usuários são inscritos nos cursos Moodle correspondentes às ofertas de disciplinas.';

$string['firstname'] = 'Nome';
$string['lastname'] = 'Sobrenome';
$string['firstnamelastname'] = 'Nome + Sobrenome';

$string['home_title'] = 'Sincronização de dados com o SAAS';
$string['no_course_offer_from_ws'] = 'Não foi localizada nenhuma oferta (edição) de curso cadastrada no SAAS. 
<BR>Verifique no SAAS se há ofertas de curso sujeitas a avaliação e se a chave da instituição foi corretamente configurada.
<p>Entre em contato com a administração do SAAS para mais informações.</p>';
$string['no_class_offer_from_ws'] = 'Não foi localizada nenhuma oferta de disciplina cadastrada no SAAS. 
<BR>Verifique no SAAS se há ofertas de disciplinas sujeitas a avaliação e se a chave da instituição foi corretamente configurada.
<p>Entre em contato com a administração do SAAS para mais informações.</p>';

$string['no_mapped_classes_offer'] = 'Não há ofertas de disciplinas mapeadas.';
$string['no_mapped_courses_offer'] = 'Não há ofertas de cursos mapeadas mapeadas.';
$string['ws_error'] = 'Houve falha na comunicação com o SAAS: \'{$a}\'<BR>Verifique se a chave da instituição e a url do webservice foram cadastradas corretamente.';
$string['update_data_error'] = 'Erro ao atualizar base de dados {$a}';
$string['get_data_error'] = 'Erro ao acessar base de dados {$a}';

$string['saas_presentation'] = 'Este módulo possibilita a exportação de dados do Moodle para o SAAS
    (Sistema de Acompanhamento e Avaliação dos Cursos da Rede e-Tec Brasil).
    Os dados exportados incluem:
    <UL>
    <LI>Identificador, nome, email e CPF de professores, estudantes e tutores vinculados às ofertas de disciplinas;</LI>
    <LI>Relacionamento de professores, estudantes e tutores a distância com ofertas as de disciplinas;</LI>
    <LI>Relacionamento de estudantes e tutores presenciais com os polos.</LI>
    </UL>
    Realize a sequência de passos abaixo. Os três primeiros permitem definir quais dados serão exportados, e o quarto passo é a efetivação da exportação.';

$string['menu_config_table'] = 'Visualizar Configurações';
$string['menu_map_course_offer'] = '1 - Mapear ofertas (edição) de cursos.';
$string['menu_map_class_offer'] = '2 - Mapear ofertas de disciplinas.';
$string['menu_map_polo'] = '3 - Mapear polos.';
$string['menu_send_data'] = '4 - Exportar dados para o SAAS.';

$string['title_passo1'] = 'Relacionamento de ofertas (edições) de cursos do SAAS com as categorias do Moodle';    
$string['title_passo2'] = 'Relacionamento de ofertas de disciplinas do SAAS com os cursos do Moodle';    
$string['title_passo3'] = 'Seleção de grupos de cursos do Moodle que representam polos da Instituição';    
$string['title_passo4'] = 'Exportação de dados para o SAAS';    

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

$string['settings'] = 'Configurações do relatório SAAS';
