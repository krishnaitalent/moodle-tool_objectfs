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
 * Object client abstract class.
 *
 * @package   tool_objectfs
 * @author    Kenneth Hendricks <kennethhendricks@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\store;

defined('MOODLE_INTERNAL') || die();

abstract class object_client_base implements object_client {

    protected $autoloader;
    protected $expirationtime;
    protected $testdelete = true;
    public $presignedminfilesize;
    public $enablepresignedurls;

    /** @var int $maxupload Maximum allowed file size that can be uploaded. */
    protected $maxupload;

    /** @var object $config Client config. */
    protected $config;

    public function __construct($config) {

    }

    /**
     * Returns true if the Client SDK exists and has been loaded.
     *
     * @return bool
     */
    public function get_availability() {
        if (file_exists($this->autoloader)) {
            return true;
        } else {
            return false;
        }
    }

    public function register_stream_wrapper() {

    }

    /**
     * Does the storage support pre-signed URLs.
     *
     * @return bool.
     */
    public function support_presigned_urls() {
        return false;
    }

    /**
     * Generates pre-signed URL to storage file from its hash.
     *
     * @param string $contenthash File content hash.
     * @param array $headers request headers.
     *
     * @throws \coding_exception
     */
    public function generate_presigned_url($contenthash, $headers = array()) {
        throw new \coding_exception("Pre-signed URLs not supported");
    }

    /**
     * Moodle admin settings form to display connection details for the client service.
     *
     * @return string
     * @throws /coding_exception
     */
    public function define_client_check() {
        global $OUTPUT;
        $output = '';
        $connection = $this->test_connection();
        if ($connection->success) {
            $output .= $OUTPUT->notification(get_string('settings:connectionsuccess', 'tool_objectfs'), 'notifysuccess');
            // Check permissions if we can connect.
            $permissions = $this->test_permissions($this->testdelete);
            if ($permissions->success) {
                $output .= $OUTPUT->notification(key($permissions->messages), 'notifysuccess');
            } else {
                foreach ($permissions->messages as $message => $type) {
                    $output .= $OUTPUT->notification($message, $type);
                }
            }
        } else {
            $output .= $OUTPUT->notification(get_string('settings:connectionfailure', 'tool_objectfs').
                $connection->details, 'notifyproblem');
        }
        return $output;
    }

    /**
     * Returns the maximum allowed file size that is to be uploaded.
     *
     * @return int
     */
    public function get_maximum_upload_size() {
        return $this->maxupload;
    }

    /**
     * Proxy range request.
     *
     * @param  \stored_file $file    The file to send
     * @param  object       $ranges  Object with rangefrom, rangeto and length properties.
     * @return false                 If couldn't get data.
     */
    public function proxy_range_request(\stored_file $file, $ranges) {
        return false;
    }

    /**
     * Test proxy range request.
     *
     * @param  object  $filesystem  Filesystem to be tested.
     * @return bool
     */
    public function test_range_request($filesystem) {
        return false;
    }

    /**
     * Tests connection to external storage.
     * Override this method in client class.
     *
     * @return object
     */
    public function test_connection() {
        return (object)['success' => false, 'details' => ''];
    }

    /**
     * Tests permissions to external storage.
     * Override this method in client class.
     *
     * @param bool $testdelete Test delete permission and fail the test if could delete object from the storage.
     * @return object
     */
    public function test_permissions($testdelete) {
        return (object)['success' => false, 'details' => ''];
    }

    /**
     * Returns true if the client is fully configured and ready to go.
     *
     * @return bool
     */
    public function client_is_ready() {
        global $CFG;
        $showdebugging = (defined('PHPUNIT_TEST') && PHPUNIT_TEST) ? false : true;

        // Return false if alternative_file_system_class is not set in config.php.
        if (empty($CFG->alternative_file_system_class)) {
            if ($showdebugging) {
                debugging('Objectfs is not ready: alternative_file_system_class is not set in config.php');
            }
            return false;
        }

        // Return false if there is a disparity between filesystem set in config.php and admin settings.
        if ($CFG->alternative_file_system_class != $this->config->filesystem) {
            if ($showdebugging) {
                debugging('Objectfs is not ready: There is a disparity between filesystem set in config.php and admin settings');
            }
            return false;
        }

        // Return false if the client SDK does not exist or has not been loaded.
        if (!$this->get_availability()) {
            if ($showdebugging) {
                debugging('Objectfs is not ready: Client SDK does not exist or has not been loaded');
            }
            return false;
        }

        // Return false if connection test failed.
        $connection = $this->test_connection();
        if (!$connection->success) {
            if ($showdebugging) {
                debugging('Objectfs is not ready: ' . $connection->details);
            }
            return false;
        }

        // Return false if permission test failed.
        $permissions = $this->test_permissions(false);
        if (!$permissions->success) {
            if ($showdebugging) {
                debugging('Objectfs is not ready: ' . $permissions->details);
            }
            return false;
        }

        // Looks like all checks have been passed. Objectfs is ready to go.
        return true;
    }
}
