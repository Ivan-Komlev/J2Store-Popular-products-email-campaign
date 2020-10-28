<?php
/**
 * @Joomla Cron Tasks
 * @author Ivan Komlev <ivankomlev@gmail.com>
 * @link http://www.joomlaboat.com
 * @GNU General Public License
 **/

//This file looks for other PHP files and executes them, file must contain a function called "cron_FILENAME", FILENAME without ".php"

// Initialize Joomla framework
const _JEXEC = 1;

/**
 * Constant that is checked in included files to prevent direct access.
 * define() is used in the installation folder rather than "const" to not error for PHP 5.2 and lower
 */

 // Saves the start time and memory usage.
$startTime = microtime(1);
$startMem  = memory_get_usage();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$dir=str_replace('/cron','',__DIR__);


define('JPATH_BASE',$dir);

if (file_exists(JPATH_BASE . '/defines.php'))
{
	include_once JPATH_BASE . '/defines.php';
}

if (!defined('_JDEFINES'))
{
    require_once JPATH_BASE . '/includes/defines.php';
}

// Get the framework.
require_once JPATH_LIBRARIES . '/import.legacy.php';

// Bootstrap the CMS libraries.
require_once JPATH_LIBRARIES . '/cms.php';

// Load the configuration
require_once JPATH_CONFIGURATION . '/configuration.php';

require_once JPATH_BASE . '/includes/framework.php';

/**
 * Cron job 
 *
 */

 			echo "CRON TASK START
<br/>";

//echo 'LOL';

			//@ob_flush();
			//flush();
			//$mainframe = JFactory::getApplication('site');
			//die;

			//@ob_flush();
			//flush();
		
			if(isset($_GET['function']))
				$function=preg_replace("/[^A-Za-z0-9 ]/", '', $_GET['function']);//JFactory Application not defined yet
			elseif(isset($_POST['function']))
				$function=preg_replace("/[^A-Za-z0-9 ]/", '', $_POST['function']);//JFactory Application not defined yet
			else
				$function='';
				
			$path=JPATH_SITE.DIRECTORY_SEPARATOR.'cron';

			$cronfiles = scandir($path);
			foreach($cronfiles as $cronfile)
			{
				$type = explode( '.', $cronfile );
				$type = array_reverse( $type );
				
				if($cronfile!='.' and $cronfile!='..' and $cronfile!='cron.php' and $type[0]=='php')
				{
					$filename=$path.DIRECTORY_SEPARATOR.$cronfile;
					if(strpos($filename,'.htm')===false and file_exists($filename))
					{
						$fn=str_replace('.php','',$cronfile);
						if($function=='' or $function==$fn)//execute all files or one specified
						{
							require_once($filename);
							$functionname='cron_'.$fn;
							echo '
Running task: "'.$functionname.'"<br/>
';
							@ob_flush();
							flush();
							$result=call_user_func($functionname);
							
							@ob_flush();
							flush();
							//TODO: do something with the Result
						}
					}
				}
			}
			
			echo "CRON TASK END
<br/>";
