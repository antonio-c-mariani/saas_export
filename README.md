Moodle SAAS Export
==================

Este módulo possibilita a exportação de dados do Moodle (versões 2.2 a 2.6) para o 
SAAS (Sistema de Acompanhamento e Avaliação dos Cursos da Rede e-Tec Brasil).
Os dados exportados incluem:

- Identificador, nome, e-mail e CPF de professores, estudantes e tutores vinculados às ofertas de disciplinas;
- Relacionamento de professores, estudantes e tutores a distância com ofertas de disciplinas;
- Relacionamento de estudantes e tutores presenciais com polos.

INSTALAÇÃO
==========
O relatório export_saas segue o procedimento padrão de instalação do Moodle:

1. Crie uma pasta (diretório) de nome  <path to your moodle dir>/report/saas_export
2. Extraia os arquivos deste plugin na pasta criada no passo 1
3. Como administrador, visite a página "Administração do site ► Avisos" da caixa de "Administração" de forma a completar a instalação.

Para configurar o plugin, acesse a página "Plugins" ► "Relatórios" ► "Relatório SAAS" da caixa de "Administração".
A "URL SAAS" e a "Chave da Instituição" devem ser obtidas com os administradores do SAAS (saas@etec.ufsc.br).

O plugin também está disponível no GitHub:
        https: https://github.com/saasexport/saas.git
        SSH:   git@github.com:saasexport/saas.git

Para clonar o repositório, execute o seguinte comando:
        git clone https://github.com/saasexport/saas.git saas_export

ACESSO
======
Para acessar o relatório e enviar dados para o SAAS, viste a página "Relatórios"=>"Relatório SAAS" da caixa de "Administração".

O acesso a este relatório é controlado pela permissão "report/saas_export:view" em nível de sistema. Assim, para delegar a tarefa
de exportar dados para o SAAS a um usuário, é necessário atribuir esta permissão a algum papel que este usuário tenha em nível de sistema.
Neste caso todos os usuários que tenham este papel poderão realizar esta tarefa. Para controlar de forma mais precisa esta delegação
pode ser necessário definir um novo papel com esta única permissão e, então, atribuir este papel aos usuários em nível de sistema.
