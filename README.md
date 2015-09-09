Moodle SAAS Export
==================

Este módulo possibilita a exportação de dados do Moodle (versões 2.0 a 2.9) para o
SAAS (Sistema de Acompanhamento e Avaliação dos Cursos da Rede e-Tec Brasil).
Os dados exportados incluem:

- Identificador, nome, e-mail e CPF de professores, estudantes e tutores vinculados às ofertas de disciplinas;
- Relacionamento de professores, estudantes e tutores a distância com ofertas de disciplinas;
- Relacionamento de estudantes e tutores presenciais com polos.
- Dados adicionais de estudantes (últimos acessos e notas)

DOWNLOAD
========

O plugin está disponível no seguinte endereço:

    https://github.com/saasexport/saas_export

Para o download na forma de um .zip, basta clicar no Botão "Download ZIP" no canto inferior direito.

INSTALAÇÃO
==========

O módulo saas para o Moodle está implementado na forma de um relatório (report). A instalação do módulo saas_export
segue o procedimento padrão de instalação do Moodle.

INSTALAÇÃO VIA ARQUIVO .zip
---------------------------

Caso o método escolhido seja via arquivo .zip, é necessário que o arquivo seja descompactado dentro
da pasta "report" da sua instalação do Moodle, tomando o cuidado de que seja criada uma sub-pasta de nome "saas_export"
(por padrão esta pasta é automaticamente criada quando se descompacta o arquivo .zip).

Nas versões 2.0 e 2.1 do Moodle, a pasta "report" encontra-se em:
    <pasta_de_instalação_do_moodle>/admin/report.
Para as versões 2.2 em diante ela encontra-se em:
    <pasta_de_instalação_do_moodle>/report.

Após a descompactação, a estrutura de pastas e arquivos deve ficar conforme abaixo:
     report/saas_export/
                    classes
                    course_mapping.php
                    css
                    db
                    img
                    index.php
                    ...
                    version.php

Caso a pasta do módulo apareça com outro nome ("saas_export-master", por exemplo), ela deve ser renomeada para
"saas_export", de forma a manter a estrutura indicada acima.

Instalação via comando git
--------------------------

Outra opção para instalação é via comando "git clone" que resulta numa cópia local do repositório disponível no GITHUB
(esta opção pressupõe que o aplicativo "git" esteja disponível em seu servidor). Estando na pasta "report",
execute uma das opções abaixo do comando "git clone":

        https:
            git clone https://github.com/saasexport/saas.git saas_export
        SSH:
            git clone git@github.com:saasexport/saas_export.git saas_export

Completando a instalação
-------------------------

Independente da forma de instalação (via arquivo .zip ou comando git) é necessário completar o processo de instalação.
Para tal, acesse o Moodle via navegador e como administrador visite a página:
    "Administração do site" => "Avisos"
da caixa de "Administração". O Moodle deve automaticamente reconhecer a existência do plugin e completar a instalação.
Caso isto não ocorra, verifique se a estrutura de pastas e arquivos está conforme indicado acima e se a pasta
"saas_export" (e suas sub-pastas e arquivos) estão com permissões tais que o servidor de www (apache, nginx, etc)
tenham acesso de leitura a elas.

Exportação via linha de comando
===============================

A exportação de dados pode ser feita via linha de comando por meio do script disponível em:
        cli/send_data.php
Este script permite a exportação para o SAAS de todas as ofertas de disciplina e de polos que tenham sido mapeados ou
seletivamente por oferta de curso. Para ver as opções executa o comando:
        php cli/send_data.php -h

Acesso ao relatório
===================

Conforme padrão do Moodle, o acesso ao módulo esta disponível no item:
    "Administração do site" => "Relatórios"=>"Exportar dados SAAS"
da caixa de "Administração". Este item só aparece, contudo, para usuários do Moodle que possuam a permissão
"report/saas_export:view" em nível global (de sistema). Veja detalhes sobre o esquema de permissões no tópico seguinte.

Permissões de acesso
====================

O módulo SAAS define três permissões que controlam o acesso às suas funções:
    report/saas_export:view     - visualizar as configurações e dados a serem exportados
    report/saas_export:config   - configurar o módulo
    report/saas_export:export   - mapear ofertas de disciplinas e polos e exportar dados para SAAS
Qualquer pessoa que utilize o módulo precisa no mínimo ter a permissão "report/saas_export:view" em nível global
(de sistema). Adicionalmente ela pode ter uma ou as duas outras permissões, conforme o tipo de ação que ela deva realizar.

O caso normal é a mesma pessoa poder tanto visualizar como configurar o módulo e exportar os dados para o SAAS.
Neste caso a sugestão é:
    1) defina (crie) um novo papel no Moodle chamado "Gerente SAAS", selecionando a opção "Sistema" no item
       "Tipos de contexto onde esse papel pode ser atribuído" e marcando como "Permitir" as três permissões acima indicadas;
    2) atribua em nível global (de sistema) este papel às pessoas que devam ter estas permissões.
As ações acima descritas estão disponíveis, respectivamente, nos itens:
    "Administração do site" => "Usuários" => "Definir papéis" e
    "Administração do site" => "Usuários" => "Atribuir papéis globais"
da caixa de "Administração".
