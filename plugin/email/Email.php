<?php
/**
 * @package nutshell-plugin
 * @author guillaume
 */
namespace nutshell\plugin\email
{
	use nutshell\core\plugin\Plugin;
	use nutshell\behaviour\Native;
	use nutshell\behaviour\Factory;
	use nutshell\plugin\email\exception\EmailException;
	use \PHPMailer;
	
	/**
	 * @package nutshell-plugin
	 * @author guillaume
	 */
	class Email extends Plugin implements Native, Factory 
	{
		
		const CONFIG_SMTP_MODE = 'mode';
		
		const CONFIG_SMTP_HOST = 'host';
		
		const CONFIG_SMTP_PORT = 'port';
		
		const CONFIG_SMTP_KEEP_ALIVE = 'keepAlive';
		
		const CONFIG_SMTP_USERNAME = 'username';
		
		const CONFIG_SMTP_PASSWORD = 'password';
		
		const CONFIG_SMTP_SECURITY = 'security';
		
		protected static $dependenciesLoaded = false;
		
		public static function loadDependencies() 
		{
			if(!self::$dependenciesLoaded) 
			{
				require_once __DIR__ . _DS_ . 'impl' . _DS_ . 'class.phpmailer.php';
				require_once __DIR__ . _DS_ . 'exception' _DS_ . 'EmailException.php';
				self::$dependenciesLoaded = true;
			}
		}
		
		public function init() 
		{
			
		}
		
		public static function runFactory($sendConfig = null) 
		{
			self::loadDependencies();
			
			return self::prepareEmail($sendConfig);
		}
		
		/**
		 * @param nutshell\core\config\Config $config
		 * @param \PHPMailer $mail
		 */
		protected static function configureSMTP($config, $mail) 
		{
			//host
			if($host = $config->{self::CONFIG_SMTP_HOST})
			{
				$mail->Host = $host;
			}
				
			if($port = $config->{self::CONFIG_SMTP_PORT})
			{
				$mail->Port = $port;
			}
				
			//connection keepalive management
			if($keepAlive = $config->{self::CONFIG_SMTP_KEEP_ALIVE})
			{
				$mail->SMTPKeepAlive = in_array(strtolower($keepAlive), array(1, true, "true", "yes", "y", "1"));
			}
				
			if($security = $config->{self::CONFIG_SMTP_SECURITY})
			{
				$mail->SMTPSecure = $security;
			}
				
			if($username = $config->{self::CONFIG_SMTP_USERNAME})
			{
				$mail->SMTPAuth = true;
				$mail->Username = $username;
				$mail->Password = $config->{self::CONFIG_SMTP_PASSWORD};
			}
		}
		
		protected static function prepareEmail($sendConfigName = null) 
		{
			//set the mailer to send exceptions on errors
			$mail = new PHPMailer(true);
			
			if(!is_null($sendConfig)) 
			{
				//retrieve plugin config
				$pluginConfig = self::config();
				
				if ($sendParams = $pluginConfig->sendConfig->{$sendConfigName})
				{
					switch($sendParams->{self::CONFIG_SMTP_MODE}) 
					{
						case MODE_PHP:
							$mail->IsMail();
							break;
							
						case MODE_SENDMAIL:
							$mail->IsSendmail();
							break;
							
						case MODE_SMTP:
							$mail->IsSMTP();
							
							self::configureSMTP($sendParams, $mail);
							break;
						
						default:
							throw new EmailException(sprintf('Unsupported send mode "%s"', $sendParams->{self::CONFIG_SMTP_MODE}));
							break;
					}
				} 
				else 
				{
					throw new EmailException(sprintf('Undefined send configuration "%s"', $sendConfigName));
				}
			}
			
			return $mail;
		} 
	}
}