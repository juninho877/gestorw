<?php
/**
 * Função para limpar ID da mensagem do WhatsApp removendo sufixos
 */
if (!function_exists('cleanWhatsAppMessageId')) {
  function cleanWhatsAppMessageId($message_id) {
    if (empty($message_id)) {
        return null;
    }
    
    // Remover sufixos como _0, _1, etc.
    $cleaned_id = preg_replace('/_\d+$/', '', $message_id);
    
    error_log("Cleaned WhatsApp message ID: '$message_id' -> '$cleaned_id'");
    
    return $cleaned_id;
  }
}
?>