Moodle SAAS Export
==================

Este módulo possibilita a exportação de dados do Moodle (versões 2.2 a 2.6) para o 
SAAS (Sistema de Acompanhamento e Avaliação dos Cursos da Rede e-Tec Brasil).
Os dados exportados incluem:

- Identificador, nome, e-mail e CPF de professores, estudantes e tutores vinculados às ofertas de disciplinas;
- Relacionamento de professores, estudantes e tutores a distância com ofertas de disciplinas;
- Relacionamento de estudantes e tutores presenciais com polos.

DOWNLOAD
========

O plugin está disponível no seguinte endereço:
    
    https://github.com/saasexport/saas_export

Para o download na forma de um .zip, basta clicar no Botão "Download ZIP" no canto inferior direito.

INSTALAÇÃO
==========

O relatório saas_export segue o procedimento padrão de instalação do Moodle:

Caso o método escolhido seja o download na forma de um .zip, é necessário que o arquivo seja descompactado dentro da pasta 'report' 
da sua instalação do Moodle. 

A pasta 'report' encontra-se no primeiro nível dentro da raiz do Moodle e pode ser encontrada da seguinte maneira:

"caminho para o seu moodle/report"

Após, a pasta deve ser renomeada de "saas_export-master" para "saas_export".


Caso queira clonar o repositório via GIT, execute um dos seguintes comando dentro da pasta 'report' da sua instalação do Moodle. 
Escolha entre https ou ssh.

        https: 
            git clone https://github.com/saasexport/saas.git saas_export
        SSH:
            git clone git@github.com:saasexport/saas_export.git saas_export


Após extrair os arquivos ou clonar o repositório, Como administrador, 
visite a página "Administração do site ► Avisos" da caixa de "Administração" de forma a completar a instalação.

Para configurar o plugin, acesse a página "Plugins" ► "Relatórios" ► "Relatório SAAS" da caixa de "Administração".
A "URL SAAS" e a "Chave da Instituição" devem ser obtidas com os administradores do SAAS (saas@etec.ufsc.br).

ACESSO
======
Para acessar o relatório e enviar dados para o SAAS, viste a página "Relatórios"=>"Relatório SAAS" da caixa de "Administração".

O acesso a este relatório é controlado pela permissão "report/saas_export:view" em nível de sistema. Assim, para delegar a tarefa
de exportar dados para o SAAS a um usuário, é necessário atribuir esta permissão a algum papel que este usuário tenha em nível de sistema.
Neste caso todos os usuários que tenham este papel poderão realizar esta tarefa. Para controlar de forma mais precisa esta delegação
pode ser necessário definir um novo papel com esta única permissão e, então, atribuir este papel aos usuários em nível de sistema.
