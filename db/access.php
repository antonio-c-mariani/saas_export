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
 * Capabilities
 *
 * @package    report_saas_export
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/environmentlib.php');

$current_version = normalize_version(get_config('', 'release'));

if (version_compare($current_version, '2.0', '>=')) {
    $capabilities = array(

        'report/saas_export:view' => array(
            'riskbitmask' => RISK_CONFIG,
            'captype' => 'read',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW
            ),
        )
    );
} else {
    $report_saas_export_capabilities = array(

        'report/saas_export:view' => array(
            'riskbitmask' => RISK_CONFIG,
            'captype' => 'read',
            'contextlevel' => CONTEXT_SYSTEM,
            'legacy' => array(
                'admin' => CAP_ALLOW
            ),
        )
    );
}
