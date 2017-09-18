<?php
/*
Plugin Name: Gravity Forms Asana Add-On
Plugin URI: https://github.com/garrettjohnson/GravityForms-Asana
Description: Add tasks to Asana directly from Gravity Forms
Version: 1.5
Author: Garrett Johnson
Author URI: http://garrett.io
*/


if (class_exists("GFForms")) {
    GFForms::include_feed_addon_framework();

    class ACAsanaAddOn extends GFFeedAddOn {

        protected $_version = "1.5";
        protected $_min_gravityforms_version = "1.9.5.1";
        protected $_slug = "asanaaddon";
        protected $_path = "gravityformsasana/asanaaddon.php";
        protected $_full_path = __FILE__;
        protected $_title = "Gravity Forms Asana Add-On";
        protected $_short_title = "Asana";

        // Members plugin integration
	      protected $_capabilities = array( 'gravityforms_asana', 'gravityforms_asana_uninstall' );

        // Permissions
      	protected $_capabilities_settings_page = 'gravityforms_asana';
      	protected $_capabilities_form_settings = 'gravityforms_asana';
      	protected $_capabilities_uninstall = 'gravityforms_asana_uninstall';
      	protected $_enable_rg_autoupgrade = true;

        public function feed_settings_fields() {
            $settings = $this->get_plugin_settings();

            $api = self::get_api();

            $project_api = $api->getProjects();
            $project_api = json_decode($project_api)->data;

            $result["label"] = "No Project Selected";
            $result["value"] = "";
            $projects[] = $result;

            foreach ($project_api as $project) {
                if ($project->name) {
                    $result["label"] = $project->name;
                    $result["value"] = $project->id;
                    $projects[] = $result;
                }
            }

            $user_api = $api->getWorkspaceUsers($settings['asana_workspace']);
            $user_api = json_decode($user_api)->data;

            foreach ($user_api as $user) {
                if ($user->name) {
                    $result["label"] = $user->name;
                    $result["value"] = $user->id;
                    $users[] = $result;
                }
            }
            $result["label"] = "No User Assigned";
            $result["value"] = "";
            $blank[] = $result;

            return array(
                array(
                    "title"  => "Asana Settings",
                    "fields" => array(
                        array(
                            "label"   => "Name",
                            "type"    => "text",
                            "name"    => "feedName",
                            "required"=> true,
                            "class"   => "medium"
                        ),
                        array(
                            "label"   => "Project",
                            "type"    => "select",
                            "name"    => "projects",
                            "class"   => "medium",
                            "required"=> true,
                            "choices" => $projects
                        ),
                        array(
                            "label"   => "Task Name",
                            "type"    => "text",
                            "name"    => "task_name",
                            "required"=> true,
                            "class"   => "medium merge-tag-support mt-position-right"
                        ),
                        array(
                            "label"   => "Notes",
                            "type"    => "textarea",
                            "name"    => "notes",
                            "class"   => "large merge-tag-support mt-position-right"
                        ),
                        array(
                            "label"   => "Due On",
                            "type"    => "text",
                            "name"    => "due_on",
                            "class"   => "medium merge-tag-support mt-position-right"
                        ),
                        array(
                            "label"   => "Assigned User",
                            "type"    => "select",
                            "name"    => "assignee",
                            "class"   => "medium",
                            "choices" => $blank + $users
                        ),
                        array(
                            "label"   => "Followers",
                            "type"    => "follower_box",
                            "name"    => "followers",
                            "choices" => $users
                        ),
                        array(
                            "name" => "condition",
                            "label" => __("Condition", "simplefeedaddon"),
                            "type" => "feed_condition",
                            "checkbox_label" => __('Enable Condition', 'simplefeedaddon'),
                            "instructions" => __("Add task to Asana if", "simplefeedaddon")
                        ),
                    )
                )
            );
        }

        public function settings_follower_box($field){
          $value         = $this->get_setting( $field['name'] );
          $values = explode(",", $value);
          $selected = "";
          foreach ($field["choices"] as $choice):
            $selected .= in_array( $choice['value'], $values ) ? "{id:" . $choice['value'] . ', name: "' . $choice['label'] . '" },' : '';
          endforeach; ?>
            <script type="text/javascript">
              jQuery(document).ready(function() {
                  jQuery("#followers").tokenInput([<?php foreach ($field["choices"] as $result): ?>
                  {id: <?php echo $result["value"]?>, name: "<?php echo $result['label']?>"},
                  <?php endforeach; ?>
                ],{
                  preventDuplicates: true,
                  hintText: "Type in a follower name",
                  prePopulate: [ <?php echo $selected ?> ]
                });
              });
            </script>
          <?php
          $this->settings_text(
            array(
              'label'         => 'Followers',
              'name'          => 'followers',
              'class'         => 'medium gaddon-setting gaddon-text'
            )
          );
        }

        public function plugin_settings_fields() {
            $api = self::get_api();
            if($api){
              $workspaces = $api->getWorkspaces();
              $workspaces = json_decode($workspaces)->data;
              foreach ($workspaces as $workspace) {
                  $result["label"] = $workspace->name;
                  $result["value"] = $workspace->id;
                  $results[] = $result;
              }
            } else {
              $result["label"] = "Add an API Key to select a workspace";
              $result["value"] = "";
              $results[] = $result;
            }

            return array(
                array(
                    "title"  => "Asana Account Information",
                    "fields" => array(
                        array(
                            "name"    => "asana_apikey",
                            "tooltip" => "This is the tooltip",
                            "label"   => "Asana API Key",
                            "type"    => "text",
                            "class"   => "medium",
                            'feedback_callback' => array( $this, 'is_valid_apikey' )
                        ),
                        array(
                            "name"    => "asana_workspace",
                            "tooltip" => "This is the tooltip",
                            "label"   => "Default Workspace",
                            "type"    => "select",
                            "class"   => "medium",
                            'choices' => $results
                        )
                    )
                )
            );
        }

        public function scripts() {
            $scripts = array(
                array("handle"  => "my_script_js",
                      "src"     => $this->get_base_url() . "/js/my_script.js",
                      "version" => $this->_version,
                      "deps"    => array("jquery"),
                      "enqueue" => array(
                          array(
                              "admin_page" => array("form_settings"),
                              "tab"        => "asanaaddon"
                          )
                      )
                ),

            );

            return array_merge(parent::scripts(), $scripts);
        }

        public function styles() {

            $styles = array(
                array("handle"  => "asana_styles",
                      "src"     => $this->get_base_url() . "/css/my_styles.css",
                      "version" => $this->_version,
                      "enqueue" => array(
                          array(
                            "tab" => "asanaaddon"
                          )
                      )
                )
            );

            return array_merge(parent::styles(), $styles);
        }

        public function process_feed($feed, $entry, $form){
          $feed["meta"]["task_name"] = GFCommon::replace_variables( $feed["meta"]["task_name"], $form, $entry, false, false);
          $feed["meta"]["notes"] = GFCommon::replace_variables( $feed["meta"]["notes"], $form, $entry, false, false, false, "text");
          $feed["meta"]["notes"] = str_replace("&#039;", "'", $feed["meta"]["notes"]);
          $feed["meta"]["notes"] = str_replace("&amp;", "&", $feed["meta"]["notes"]);

          // Due on Matching
          $feed["meta"]["due_on"] = GFCommon::replace_variables( $feed["meta"]["due_on"], $form, $entry, false, false, false, "text");
          if (($timestamp = strtotime($feed["meta"]["due_on"])) === false) {
            $feed["meta"]["due_on"] = "";
          } else {
            $feed["meta"]["due_on"] = date("Y-m-d", $timestamp);
          }

          $projects = array();
          $projects[] = $feed["meta"]["projects"];
          $feed["meta"]["projects"] = $projects;

          $followers = array();
          $followers = explode(",", $feed["meta"]["followers"]);
          $feed["meta"]["followers"] = $followers;

          $this->export_feed( $entry, $form, $feed );
        }

        public function export_feed( $entry, $form, $feed ) {
          $settings = $this->get_plugin_settings();

          $api = self::get_api();
          $task_info = array(
            'workspace' => $settings['asana_workspace'], // Workspace ID
            'name' => $feed["meta"]["task_name"], // Name of task
            'notes' => $feed["meta"]["notes"], // Notes from the task
            'due_on' => $feed["meta"]["due_on"],
            'assignee' => $feed["meta"]["assignee"], // Assign task to...
            'projects' => $feed["meta"]["projects"],
            'followers'=> $feed["meta"]["followers"]
          );
          $task_info = array_filter($task_info);
          $result = $api->createTask($task_info);

          // As Asana API documentation says, when a task is created, 201 response code is sent back so...
          if ($api->responseCode != '201' || is_null($result)) {
              self::log_error("The following error occurred: " . print_r($api->responseCode, true) . print_r($feed["meta"], true));
              $retval = false;
          } else {
              self::log_debug("Successful response from Asana: " . print_r($api->responseCode, true));
          }
        }

        public function is_valid_date($value){
            // previous to PHP 5.1.0 you would compare with -1, instead of false
            if (($timestamp = strtotime($value)) === false) {
                return false;
            } else {
                return true;
            }
        }

        public function can_duplicate_feed( $id ) {
          return true;
        }

        public function feed_settings_title() {
          return "Asana Task Settings";
        }

        public function plugin_settings_icon() {
          $output = '<img src="'. plugins_url('/css/asana-mark256.png', __FILE__) . '" style="height: 40px;display: inline;margin-top: -7px;vertical-align: middle;margin-right: 5px;">';
          return $output;
        }

        public function feed_list_title() {
          $url = add_query_arg( array( 'fid' => '0' ) );
          $url = esc_url( $url );
          return "Asana Tasks" . " <a class='add-new-h2' href='{$url}'>" . esc_html__( 'Add New' , 'gravityforms' ) . '</a>';
        }

        public function feed_list_columns() {
           return array(
             'feedName'		=> "Name",
             'task_name'       => "Task Subject",
             'assignee'   => "Assignee"
           );
         }

        public function get_column_value_feedName( $feed ) {
            return "<strong>" . $feed['meta']['feedName'] . "</strong>";
        }

        public function get_column_value_assignee( $feed ) {
           $api = self::get_api();
           /* If Asana instance is not initialized, return channel ID. */
           if ( ! $api ) {
             return ucfirst( $feed['meta']['assignee'] );
           }

           $user = $api->getUserInfo( $feed['meta']['assignee'] );
           $user = json_decode($user, true);
           return $user["data"]["name"];
         }

        public function feed_list_no_item_message() {
          $url = add_query_arg( array( 'fid' => 0 ) );
          return sprintf( esc_html__( "You don't have any tasks configured. Let's go %screate one%s!", 'gravityforms' ), "<a href='" . esc_url( $url ) . "'>", '</a>' );
        }

        private function get_api(){
          //global asana settings
          $api_key = $this->get_plugin_settings("asana_token");
          $api_key = $api_key["asana_apikey"];

          $api = null;

            if(!empty($api_key)){
                if(!class_exists("Asana")){
                    require_once("api/asana.php");
                }
                self::log_debug("Retrieving API Info for key " . $api_key);
                $api = new Asana(array('accessToken' => trim($api_key) ));
            } else {
                self::log_debug("API credentials not set");
                return null;
            }

            if(!$api){
                self::log_error("Failed to set up the API");
                return null;
            }

            self::log_debug("Successful API response received");

            return $api;
        }

        public function is_valid_apikey($value){
          if(!class_exists("Asana")){
            require_once("api/asana.php");
          }
          self::log_debug("Validating login for API Info for key {$value}");
          if($value !== "") {
            $api = new Asana(array('accessToken' => trim($value) ));
            $user = $api->getUserInfo();
            self::log_debug("Validating login for accessToken" . print_r($user, true));
            // As Asana API documentation says, when response is successful, we receive a 200 in response so...
            if ($api->responseCode != '200' || is_null($user)) {
              echo 'Error while trying to connect to Asana, response code: ' . $api->responseCode;
              return false;
            } else {
              self::log_debug("Login valid: true");
              return true;
            }
          }
        }

    }

    new ACAsanaAddOn();
}
