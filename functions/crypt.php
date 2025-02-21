<?php


class crypt
{
    private $password;

    public function __construct($password)
    {
        $this->password = $password;
    }

    function Crypt($In)
    {
        $Out = '';
        // get string length
        $StrLen = strlen($In);

        // get string char by char
        for ($i = 0; $i < $StrLen ; $i++)
        {
            //current char
            $chr = substr($In,$i,1);

            //get password char by char
            $modulus = $i % strlen($this->password);
            $passwordchr = substr($this->password,$modulus, 1);

            //encryption algorithm
            $Out .= chr(ord($chr)+ord($passwordchr));
        }
        return base64_encode($Out);
    }




    function Decrypt($In)
    {
        $In = base64_decode($In);
        $Out = '';
        // get string length
        $StrLen = strlen($In);

        // get string char by char
        for ($i = 0; $i < $StrLen ; $i++)
        {
            //current char
            $chr = substr($In,$i,1);

            //get password char by char
            $modulus = $i % strlen($this->password);
            $passwordchr = substr($this->password,$modulus, 1);

            //encryption algorithm
            $Out .= chr(ord($chr)-ord($passwordchr));
        }
        return $Out;
    }
}