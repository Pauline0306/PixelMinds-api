<?php 

class Secret {

    function generateSecretKey($length = 32){
        return bin2hex(random_bytes($length));
    }

}


?>