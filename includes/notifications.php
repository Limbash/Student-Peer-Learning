<?php
class Notification {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function create($user_id, $group_id, $type, $title, $message) {
        $stmt = $this->conn->prepare("INSERT INTO notifications (user_id, group_id, type, title, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $user_id, $group_id, $type, $title, $message);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    public function sendEmailNotification($user_id, $subject, $message) {
        // Get user email
        $stmt = $this->conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($email);
        $stmt->fetch();
        $stmt->close();
        
        if ($email) {
            // Basic email sending (configure with your SMTP in production)
            $headers = "From: Peer Learning Network <noreply@peerlearning.com>\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            $html_message = "
                <html>
                <body>
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <h2 style='color: #4e73df;'>Peer Learning Network</h2>
                        <div style='background: #f8f9fc; padding: 20px; border-radius: 5px;'>
                            $message
                        </div>
                        <p style='margin-top: 20px; color: #6c757d;'>
                            <small>You can manage your notification preferences in your account settings.</small>
                        </p>
                    </div>
                </body>
                </html>
            ";
            
            mail($email, $subject, $html_message, $headers);
        }
    }
    
    public function notifyGroupMembers($group_id, $exclude_user_id, $type, $title, $message) {
        // Get all group members except the excluded user
        $members = $this->conn->query("
            SELECT u.id, u.email, u.notification_prefs 
            FROM group_members gm
            JOIN users u ON gm.user_id = u.id
            WHERE gm.group_id = $group_id AND u.id != $exclude_user_id
        ");
        
        while ($member = $members->fetch_assoc()) {
            $prefs = json_decode($member['notification_prefs'], true);
            
            // Check if user wants this type of notification
            if ($prefs['email'] && $prefs[$type]) {
                // Create in-app notification
                $this->create($member['id'], $group_id, $type, $title, $message);
                
                // Send email notification
                $this->sendEmailNotification($member['id'], $title, $message);
            }
        }
    }
}
?>