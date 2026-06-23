<?php

/*
Plugin Name:  Pré Cadastro Hubb Imobiliário
Description:  Formulário de pré-cadastro
Version:      1.0.0
Author:       Marcos Oliveira
Author URI:   https://www.marcosvoliveira.com/
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  pre-cadastro-hubb
*/


// Adicionando opção de Pré-cadastro no menu Usuários
function pre_cadastro_menu_option(){
    add_users_page('Pré-Cadastro Hubb Imobiliário','Pré-Cadastro','manage_options','pre-cadastro-admin-menu','pre_cadastro_formulario_page','id',1);
}

add_action('admin_menu', 'pre_cadastro_menu_option');


// Verificar se usuário está logado e se a função de registrar está ativada 
function vicode_registration_form() {
 	if(!is_user_logged_in()) {
 		$registration_enabled = get_option('users_can_register');
 		if($registration_enabled) {
			$output = vicode_registration_fields();
		} else {
			$output = __('Ative a função "Qualquer pessoa pode se registrar" nas Configurações.');
		}
		return $output;
	}
}
add_shortcode('register_form', 'vicode_registration_form');


// Registrar funções do formulário
function vicode_registration_fields() {
	
	ob_start(); ?>	

		<?php 
    		vicode_register_messages(); 
		?>

		
		<div class="wrap">
		    <div id="ajax-response"></div>
		    
            <h1 id="add-new-user">Adicionar novo usuário</h1>
            
            <p>Crie um pré-cadastro de um novo usuário. Ele receberá um e-mail para cadastrar uma senha.</p>
            
            
            <form id="vicode_registration_form" class="vicode_form" action="" method="POST">
    			<fieldset>

    				<table id="createuser" class="form-table" role="presentation">
                	    <tbody>
                    	    <tr class="form-field form-required">
            					<th scope="row"><label for="vicode_user_Login">CPF*</label></th>
            					<td><input name="vicode_user_login" id="vicode_user_login" class="vicode_user_login" type="text" required /></td>
    				        </tr>
    				        
    				        
    				        <tr class="form-field form-required">
            					<th scope="row"><label for="vicode_user_email"><?php _e('Email'); ?>*</label></th>
            					<td><input name="vicode_user_email" id="vicode_user_email" class="vicode_user_email" type="email" required /></td>
    				        </tr>
    				        
    				        <tr class="form-field form-required">
            					<th scope="row"><label for="vicode_user_first"><?php _e('First Name'); ?>*</label></th>
            					<td><input name="vicode_user_first" id="vicode_user_first" type="text" class="vicode_user_first" required /></td>
    				        </tr>
    				        
    				        <tr class="form-field form-required">
            					<th scope="row"><label for="vicode_user_last"><?php _e('Last Name'); ?>*</label></th>
            					<td><input name="vicode_user_last" id="vicode_user_last" type="text" class="vicode_user_last" required /></td>
    				        </tr>
    				        
    				        
                	    </tbody>
                	</table>
                	
                
    				<p class="submit">
    					<input type="hidden" name="vicode_csrf" value="<?php echo wp_create_nonce('vicode-csrf'); ?>"/>
    					<input type="submit" class="button button-primary" value="Adicionar usuário"/>
    				</p>
    			</fieldset>
    		</form>

        </div>
        
	<?php
	return ob_get_clean();
}

// Registrando o usuário sem senha
// O username será o CPF da pessoa, para evitar duplicidades
function vicode_add_new_user() {
    if (isset( $_POST["vicode_user_login"] ) && wp_verify_nonce($_POST['vicode_csrf'], 'vicode-csrf')) {
      $user_login		= $_POST["vicode_user_login"];	
      $user_email		= $_POST["vicode_user_email"];
      $user_first 	    = $_POST["vicode_user_first"];
      $user_last	 	= $_POST["vicode_user_last"];
      $user_pass		= '';
      
      // Verificação de registro
      require_once(ABSPATH . WPINC . '/registration.php');
      
      if(username_exists($user_login)) {
          // Caso o CPF já esteja cadastrado
          vicode_errors()->add('username_unavailable', __('CPF já cadastrado!'));
      }
      if(!validate_username($user_login)) {
          // CPF invalido
          vicode_errors()->add('username_invalid', __('CPF Inválido!'));
      }
      if($user_login == '') {
          // CPF vazio
          vicode_errors()->add('username_empty', __('O CPF é obrigatório!'));
      }
      if(!is_email($user_email)) {
          // E-mail inválido
          vicode_errors()->add('email_invalid', __('O e-mail é obrigatório!'));
      }
      if(email_exists($user_email)) {
          // E-mail já foi registrado
          vicode_errors()->add('email_used', __('Este e-mail já foi registrado!'));
      }

      
      $errors = vicode_errors()->get_error_messages();
      
      // Exibir erros
      if(empty($errors)) {
          
          $new_user_id = wp_insert_user(array(
                  'user_login'		=> $user_login,
                  'user_email'		=> $user_email,
                  'first_name'		=> $user_first,
                  'last_name'			=> $user_last,
                  'user_registered'	=> date('Y-m-d H:i:s'),
                  'role'				=> 'client_hubb' // Cargo personalizado
              )
          );
          if($new_user_id) {
              // Finalização do pré-cadastro e envio de e-mail para definição de senha 
              wp_new_user_notification($new_user_id);
              wp_send_new_user_notifications($new_user_id);
              echo '<div class="notice updated my-acf-notice is-dismissible" > <p><strong>Sucesso</strong>:  Usuário cadastrado.</p> </div>';
          }
          
      }
  
  }
}
add_action('init', 'vicode_add_new_user');

// Erros do Wordpress
function vicode_errors(){
    static $wp_error; // Vaiável global
    return isset($wp_error) ? $wp_error : ($wp_error = new WP_Error(null, null, null));
}

// Mostrar erros no pré-cadastro
function vicode_register_messages() {
	if($codes = vicode_errors()->get_error_codes()) {
		echo '<div class="vicode_errors">';
		   foreach($codes as $code){
		        $message = vicode_errors()->get_error_message($code);
		        echo '<div class="notice error my-acf-notice is-dismissible" > <p><strong>' . __('Error') . '</strong>: ' . $message . '</p> </div>';
		    }
		echo '</div>';
	}	
}


// Exibir campos
function pre_cadastro_formulario_page(){
    echo vicode_registration_fields();
}


function cwpai_custom_new_user_email( $wp_new_user_notification_email, $user, $blogname ) {

    // Estilos 
    $email_style = "style='background-color:#f7f7f7;border-radius:6px;border:1px solid #ccc;color:#333;'";
    $content_style = "style='background-color:#fff;padding:20px;border-radius:4px;border:1px solid #ddd;margin:10px 0;'";
    $link_style = "style='background-color:#ff6f02;border-radius:3px;color:#fff;display:inline-block;font-size:14px;font-weight:bold;margin-top:20px;padding:12px 20px;text-decoration:none;text-transform:uppercase;'";
    $small_style = "style='display:inline-block;font-size:14px;margin-top:20px;padding-left:20px;text-decoration:none;'";

    // Conteúdo do E-mail
    $message = "<div $content_style>";
    $message .= "<h2>" . sprintf( __( 'Bem-vindo à %s!' ), $blogname ) . "</h2>";
    $message .= "<p>" . __( 'Seu cadastro está quase completo, você precisa definir sua senha.' ) . "</p>";
    $message .= "<h3>" . __( 'No Portal Hubb Imobiliário você poderá:' ) . "</h3>";
    $message .= "<ul><li>" . __( 'Ver andamento do seu empreendimento' ) . "</li><li>" . __( 'Consultar débitos' ) . "</li><li>" . __( 'Emitir 2ª Via de Boleto' ) . "</li><li>" . __( 'Resumo financeiro' ) . "</li><li>" . __( 'Adiantar parcelas' ) . "</li><li>" . __( 'Abrir chamados' ) . "</li></ul>";
    $message .= "<p>" . __( 'Para fazer seu primeiro acesso, clique no link abaixo e defina sua senha no portal.' ) . "</p>";
    $message .= "<a $link_style href='" . network_site_url( "wp-login.php?action=rp&key=$user->user_activation_key&login=" . rawurlencode( $user->user_login ), 'login' ) . "'>" . __( 'Definir minha senha' ) . "</a>";
    $message .= "</div>";

    $header = "<a href='" . home_url() . "'><img src='https://hubbimobiliario.com.br/portal/wp-content/uploads/2024/04/logo-hubb-imobiliario-laranja.png' height='50' alt='" . $blogname . "'></a>";

    // Dados de envio
    $to = $user->user_email;
    $subject = sprintf( __( 'Bem-vindo à %s!' ), $blogname );
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    $message = $header . $message;

    $wp_new_user_notification_email['message'] = apply_filters( 'cwpai_email', $message );
    $wp_new_user_notification_email['subject'] = apply_filters( 'cwpai_email', $subject );
    $wp_new_user_notification_email['headers'] = apply_filters( 'cwpai_email_headers', $headers );

    return $wp_new_user_notification_email;
}

add_filter( 'wp_new_user_notification_email', 'cwpai_custom_new_user_email', 10, 3 );


// CSS personalizado
function themeslug_enqueue_style() {
    $versao = rand();
	  wp_enqueue_style( 'core', "/wp-content/plugins/pre-cadastro-hubb/reset-styles.css?v=$versao", false ); 

    // Redirecionamento após login
    if($_GET['action']==='resetpass' && strpos($_SERVER['REQUEST_URI'],'wp-login.php')) {
        echo '<meta http-equiv="refresh" content="0;url=/portal/login/">';
        exit;
    }
}
add_action( 'login_enqueue_scripts', 'themeslug_enqueue_style', 10 );
?>
