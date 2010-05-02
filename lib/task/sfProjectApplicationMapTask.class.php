<?php
/*
 * This file is part of sfApplicationMapPlugin
 * (c) 2009 Tomasz Ducin
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @package    sfApplicationMapPlugin
 * @author     Tomasz Ducin <tomasz.ducin@gmail.com>
 * @version    SVN: $Id: sfProjectApplicationMapTask.class.php 25105 2009-12-08 21:26:43Z tkoomzaaskz $
 */
class sfProjectApplicationMapTask extends sfBaseTask
{
  /**
   * Relative repositry path for the generated files
   *
   * @var string
   */
  const GRAPH_DIR = "doc/graph";

  /**
   * Name of the file containing dot code
   *
   * @var string
   */
  const DOT_FILE = "applications.dot";

  /**
   * Name of the image file created by the dot command
   *
   * @var string
   */
  const IMG_DOT_FILE = "applications.dot.png";

  /**
   * Name of the image file created by the neato command
   *
   * @var string
   */
  const IMG_NEATO_FILE = "applications.neato.png";

  /**
   * Name of the image file created by the twopi command
   *
   * @var string
   */
  const IMG_TWOPI_FILE = "applications.twopi.png";

  /**
   * Name of the image file created by the circo command
   *
   * @var string
   */
  const IMG_CIRCO_FILE = "applications.circo.png";

  /**
   * Name of the image file created by the fdp command
   *
   * @var string
   */
  const IMG_FDP_FILE = "applications.fdp.png";

  /**
   * Max length (in characters) of each line of action comment
   *
   * @var integer
   */
  const MAX_LINE_LENGTH = 12;

  /**
   * Label shown for admin-generator modules
   *
   * @var string
   */
  const ADMIN_LABEL = "admin";

  /**
   * Configures the current task.
   */
  protected function configure()
  {
    $this->aliases = array('project-map');
    $this->namespace        = 'project';
    $this->name             = 'application-map';
    $this->briefDescription = 'Application-module-action structure visualisation.';
    $this->detailedDescription = <<<EOF
The [project:application-map|INFO] task outputs application-module-action
structure using graphviz. Call it with:

  [./symfony project:application-map|INFO]
EOF;
  }

  /**
   * Checks if a given directory is correct
   *
   * @param String $dir - name of the directory
   * @return Boolean - whether given parameter is a correct directory
   */
  function correct_dir($dir)
  {
    return ($dir != '.' && $dir != '..' && $dir != '.svn');
  }

  function getFormattedComment($text)
  {
    $result = '';
    $length = strlen($text);
    $ind = 0;
    $act_lin_length = 0;
    while ($ind < $length)
    {
      if ($text[$ind] == " " && $act_line_length > self::MAX_LINE_LENGTH)
      {
        $result .= "</td></tr><tr><td>";
        $act_line_length = 0;
      }
      else
      {
        $result .= $text[$ind];
      }
      $ind++;
      $act_line_length++;
    }
    return $result;
  }

  /**
   * Analyses the appliaction-module-action structure of the project and
   * creates an output array.
   *
   * @return Array
   */
  protected function getContent()
  {
    // application data array
    $content = array();

    // applications reading
    $dir_app = dirname(__FILE__).'/../../../../apps/';

    // handle for application directories
    $handle_app = opendir($dir_app);

    // loop iterating applications
    while (false !== ($file_app = readdir($handle_app)))
    {
      // everything except for '.', '..' and '.svn'
      if ($this->correct_dir($file_app))
      {
        // single application content subarray
        $content[$file_app] = array();

        // modules reading
        $dir_mod = $dir_app.$file_app.'/modules/';

        // handle for module directories
        $handle_mod = opendir($dir_mod);

        // loop iterating modules
        while (false !== ($file_mod = readdir($handle_mod)))
        {
          // everything except for '.', '..' and '.svn'
          if ($this->correct_dir($file_mod))
          {
            // single module content subarray
            $content[$file_app][$file_mod] = array();

            // checking if module is admin-generated
            $generator_file_path = $dir_mod.$file_mod.'/config/generator.yml';
            if (file_exists($generator_file_path))
            {
              $content[$file_app][$file_mod] = 'admin-generator';
            }
            else // actions reading
            {
              $actions_dir = $dir_mod.$file_mod.'/actions/';
              $element_files = array();

              // actions
              $actions_file_path = $actions_dir.'actions.class.php';
              if (file_exists($actions_file_path))
              {
                $element_files['action'] = file($actions_file_path);
              }

              // components
              $components_file_path = $actions_dir.'components.class.php';
              if (file_exists($components_file_path))
              {
                $element_files['component'] = file($components_file_path);
              }

              foreach($element_files as $type => $file)
              {
                $file_content = implode("", $file);
                $matches = array();
                $pattern = "/\/\*\*((.|\n)*?)public function execute([0-9A-Za-z_]*)/";
                preg_match_all($pattern, $file_content, $matches);
                $content[$file_app][$file_mod][$type]['comments'] = $matches[1];
                $content[$file_app][$file_mod][$type]['elements'] = $matches[3];
                $pattern_internal = "/\/\*\*((.|\n)*?)\*\//";
                preg_match($pattern_internal, $content[$file_app][$file_mod][$type]['comments'][0], $content[$file_app][$file_mod][$type]['comments'][0]);
                $content[$file_app][$file_mod][$type]['comments'][0] = $content[$file_app][$file_mod][$type]['comments'][0][0];
              }
              // lowercasing action/component names
              foreach($content[$file_app][$file_mod] as $type => &$elements)
              {
                foreach($elements['elements'] as $key => &$element)
                {
                  $element[0] = strtolower($element[0]); // lowercase only the first letter
                  // lcfirst available since (PHP 5 >= 5.3.0)
                }
              }

              // retrieving comment text
              foreach($content[$file_app][$file_mod] as $type => &$elements)
              {
                foreach($elements['comments'] as $key => &$comment)
                {
                  // eliminating any non-execute method comments
                  $pattern = "/\/\*\*((.|\n)*?)\*\//";
                  preg_match_all($pattern, $comment, $matches);
                  if(count($matches[1]))
                    $comment = $matches[1][0];

                  // creating temporary lines array
                  $lines = explode("\n", $comment);

                  // destroying first line which is either whitespaced or '/**'
                  unset($lines[0]);

                  // result comment array
                  $lines_res = array();
                  for($ind = 1; $ind <= count($lines) && trim($lines[$ind]) != "*"; $ind++)
                  {
                    $lines_res[] = trim(substr(trim($lines[$ind]), 2));
                  }
                  $comment = implode(" ", $lines_res);
                }
              }
            }
          }
        }
        // disposal of module directories handle
        closedir($handle_mod);
      }
    }
    // disposal of application directories handle
    closedir($handle_app);
    return $content;
  }

  /**
   * Constructs a graph using Image_GraphViz class, which content is based on
   * appliaction-module-action structure of the project.
   *
   * @param Array $content - appliaction-module-action structure
   * @param Array $config - graph settings read from map.ini file
   * @return Image_GraphViz
   */
  public function getGraph($content, $config)
  {
    // new graph object
    $graph = new Image_GraphViz(false, null, 'G', false);

    $config_file = sfConfig::get('sf_config_dir').'/properties.ini';
    $config_content = parse_ini_file($config_file, true);
    $root_node = strtoupper(isset($config_content['symfony']['name']) ? $config_content['symfony']['name'] : 'Project name');

    $graph->addNode(
      $root_node,
      array(
        'shape' => $config['root_shape'],
        'comment' => 'root node',
        'style' => $config['root_style'],
        'fillcolor' => $config['root_fillcolor']),
      $app_name);

    // loop iterating applications
    foreach ($content as $app_name => $app_content)
    {
      // adding application nodes
      $graph->addNode(
        $app_name,
        array(
          'shape' => $config['app_shape'],
          'comment' => $app_name.' application',
          'style' => $config['app_style'],
          'fillcolor' => $config['app_fillcolor']),
        $app_name);

      // loop iterating each module
      foreach ($app_content as $mod_name => $mod_content)
      {
        // checking whether the module is an admin-generator or not
        if ($mod_content == "admin-generator")
        {
          $graph->addNode(
            $app_name.'_'.$mod_name,
            array(
              'headlabel' => $mod_name,
              'label' => "<table border=\"0\" cellborder=\"0\"><tr><td><font color=\"red\" face=\"Courier-New\" point-size=\"16\">".$mod_name."</font></td></tr>\n<tr><td>".self::ADMIN_LABEL."</td></tr></table>",
              'shape' => $config['admin_shape'],
              'comment' => $mod_name.' module',
              'style' => $config['admin_style'],
              'fillcolor' => $config['admin_fillcolor']));
        }
        else // module is not admin-generator
        {
          $graph->addNode(
            $app_name.'_'.$mod_name,
            array(
              'label' => $mod_name,
              'shape' => $config['module_shape'],
              'comment' => $mod_name.' module',
              'style' => $config['module_style'],
              'fillcolor' => $config['module_fillcolor']));
          // loop iterating each action/component
          foreach($mod_content as $type => $elements)
          {
            foreach($elements['elements'] as $elem_index => $elem_name)
            {
              $elem_comment = $this->getFormattedComment($mod_content[$type]['comments'][$elem_index]);
              $graph->addNode(
                $app_name.'_'.$mod_name.'_'.$elem_name,
                array(
                  'label' => "<table border=\"0\" cellborder=\"0\"><tr><td><font color=\"chartreuse4\" face=\"Courier-New\" point-size=\"16\">".$elem_name."</font></td></tr>\n<tr><td width=\"5\">$elem_comment</td></tr></table>",
                  'shape' => $config[$type.'_shape'],
                  'comment' => $elem_name.' module',
                  'width' => '1.0',
                  'style' => $config[$type.'_style'],
                  'fillcolor' => $config[$type.'_fillcolor']));
              // link module to an action/component
              $graph->addEdge(array($app_name.'_'.$mod_name => $app_name.'_'.$mod_name.'_'.$elem_name));
            }
          }
        }
        // link application to a module
        $graph->addEdge(array($app_name => $app_name.'_'.$mod_name));
      }
      // link root node to an application
      $graph->addEdge(array($root_node => $app_name));
    }
    return $graph;
  }

  /**
   * Executes the current task.
   *
   * @param array $arguments  An array of arguments
   * @param array $options    An array of options
   */
  protected function execute($arguments = array(), $options = array())
  {
    $this->logSection('application-map', 'reading configuration');

    // read the map.ini file
    $ini_file = sfConfig::get('sf_root_dir').'/plugins/sfApplicationMapPlugin/config/map.ini';
    $ini_content = parse_ini_file($ini_file, true);

    $this->logSection('application-map', 'generating images');

    // creating directory for the graphviz plugin output
    $baseDir = sfConfig::get('sf_root_dir') . '/' . self::GRAPH_DIR;
    if (false === is_dir($baseDir))
    {
      if (false === $this->getFilesystem()->mkdirs($baseDir, 0755))
      {
        throw new RuntimeException(sprintf('Can not create dir [%s]', $baseDir));
      }
    }

    // generating content of application-module-action structure
    $content = $this->getContent();

    // graph
    $graph = $this->getGraph($content, $ini_content);

    // saving dot code to a file
    file_put_contents($baseDir . '/' . self::DOT_FILE, $graph->parse());

    // executing image files generating
    $this->getFilesystem()->execute('dot ' . $baseDir . '/' . self::DOT_FILE . ' -Tpng -o' . $baseDir . '/' . self::IMG_DOT_FILE);
    $this->getFilesystem()->execute('neato ' . $baseDir . '/' . self::DOT_FILE . ' -Tpng -o' . $baseDir . '/' . self::IMG_NEATO_FILE);
    $this->getFilesystem()->execute('twopi ' . $baseDir . '/' . self::DOT_FILE . ' -Tpng -o' . $baseDir . '/' . self::IMG_TWOPI_FILE);
    $this->getFilesystem()->execute('circo ' . $baseDir . '/' . self::DOT_FILE . ' -Tpng -o' . $baseDir . '/' . self::IMG_CIRCO_FILE);
    $this->getFilesystem()->execute('fdp ' . $baseDir . '/' . self::DOT_FILE . ' -Tpng -o' . $baseDir . '/' . self::IMG_FDP_FILE);
  }
}

