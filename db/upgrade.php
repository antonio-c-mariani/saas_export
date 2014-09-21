<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file keeps track of upgrades to
 * the forum module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package    report
 * @subpackage saas_export
 */

function xmldb_report_saas_export_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 20140092100) {

        // --------------------------------------------------------------------------------------------
        $table = new xmldb_table('saas_config_roles');
        if (!$dbman->table_exists($table)) {
            $dbman->install_one_table_from_xmldb_file(dirname(__FILE__). '/install.xml', 'saas_config_roles');
        }

        // --------------------------------------------------------------------------------------------
        $table = new xmldb_table('saas_disciplinas');
        if (!$dbman->table_exists($table)) {
            $dbman->install_one_table_from_xmldb_file(dirname(__FILE__). '/install.xml', 'saas_disciplinas');
        }

        // --------------------------------------------------------------------------------------------
        $table = new xmldb_table('saas_map_catcourses_polos');
        if (!$dbman->table_exists($table)) {
            $dbman->install_one_table_from_xmldb_file(dirname(__FILE__). '/install.xml', 'saas_map_catcourses_polos');
        }

        // --------------------------------------------------------------------------------------------
        $table = new xmldb_table('saas_map_course');
        if (!$dbman->table_exists($table)) {
            $dbman->install_one_table_from_xmldb_file(dirname(__FILE__). '/install.xml', 'saas_map_course');
        }

        // --------------------------------------------------------------------------------------------
        $table = new xmldb_table('saas_map_groups_polos');
        if (!$dbman->table_exists($table)) {
            $dbman->install_one_table_from_xmldb_file(dirname(__FILE__). '/install.xml', 'saas_map_groups_polos');
        }

        // --------------------------------------------------------------------------------------------
        $table = new xmldb_table('saas_polos');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
            $dbman->install_one_table_from_xmldb_file(dirname(__FILE__). '/install.xml', 'saas_polos');
        } else {
            $dbman->install_one_table_from_xmldb_file(dirname(__FILE__). '/install.xml', 'saas_polos');
        }

        // --------------------------------------------------------------------------------------------
        $table = new xmldb_table('saas_ofertas_cursos');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
            $dbman->install_one_table_from_xmldb_file(dirname(__FILE__). '/install.xml', 'saas_ofertas_cursos');
        } else {
            $dbman->install_one_table_from_xmldb_file(dirname(__FILE__). '/install.xml', 'saas_ofertas_cursos');
        }

        // --------------------------------------------------------------------------------------------
        $table = new xmldb_table('saas_ofertas_disciplinas');
        if ($dbman->table_exists($table)) {

            $field = new xmldb_field('year');
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }

            $field = new xmldb_field('name');
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }

            $field = new xmldb_field('saas_course_offer_id');
            if ($dbman->field_exists($table, $field)) {
                $index = new xmldb_index('saas_course_offer_id', XMLDB_INDEX_NOTUNIQUE, array('saas_course_offer_id'));
                if ($dbman->index_exists($table, $index)) {
                    $dbman->drop_index($table, $index);
                }

                $dbman->drop_field($table, $field);
            }

            $field = new xmldb_field('ending', XMLDB_TYPE_CHAR, '15');
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_precision($table, $field);
                $dbman->rename_field($table, $field, 'fim');
            }

            $field = new xmldb_field('beginning', XMLDB_TYPE_CHAR, '15');
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_precision($table, $field);
                $dbman->rename_field($table, $field, 'inicio');
            }

            $field = new xmldb_field('saas_id', XMLDB_TYPE_CHAR, '255');
            if ($dbman->field_exists($table, $field)) {
                $index = new xmldb_index('saas_id', XMLDB_INDEX_UNIQUE, array('saas_id'));
                if ($dbman->index_exists($table, $index)) {
                    $dbman->drop_index($table, $index);
                }

                $dbman->rename_field($table, $field, 'uid');

                $index = new xmldb_index('uid', XMLDB_INDEX_UNIQUE, array('uid'));
                if (!$dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
            }

            $field = new xmldb_field('disciplina_uid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'uid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('oferta_curso_uid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'fim');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('group_map_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'oferta_curso_uid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);

                $sql = "UPDATE {saas_ofertas_disciplinas} set group_map_id = id";
                $DB->execute($sql);

                $field = new xmldb_field('courseid');
                if ($dbman->field_exists($table, $field)) {
                    $sql = "INSERT INTO {saas_map_course} (courseid, group_map_id)
                            SELECT courseid, group_map_id FROM {saas_ofertas_disciplinas}";
                    $DB->execute($sql);

                    $index = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));
                    if ($dbman->index_exists($table, $index)) {
                        $dbman->drop_index($table, $index);
                    }

                    $dbman->drop_field($table, $field);
                }

                $index = new xmldb_index('disciplina_uid', XMLDB_INDEX_NOTUNIQUE, array('disciplina_uid'));
                if (!$dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
                $index = new xmldb_index('oferta_curso_uid', XMLDB_INDEX_NOTUNIQUE, array('oferta_curso_uid'));
                if (!$dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
                $index = new xmldb_index('group_map_id', XMLDB_INDEX_NOTUNIQUE, array('group_map_id'));
                if (!$dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
                $index = new xmldb_index('enable', XMLDB_INDEX_NOTUNIQUE, array('enable'));
                if (!$dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
            }

        } else {
            $dbman->install_one_table_from_xmldb_file(dirname(__FILE__). '/install.xml', 'saas_ofertas_disciplinas');
        }

        upgrade_plugin_savepoint(true, 20140092100, 'report', 'saas_export');
    }

    return true;
}
