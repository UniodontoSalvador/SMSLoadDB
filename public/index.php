<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Aws\Sqs\SqsClient;

require './vendor/autoload.php';

$app = new \Slim\App;
$app->get('/SQStoMySQL', function ($request, $response, $args) {
    $client = SqsClient::factory(array(
    'profile' => 'default', //O seu perfil. Dê uma olhada na pasta .aws, no arquivo credentials.
    'region'  => 'us-west-2' //Região
	));
	$queueUrl= 'url'; //A url do seu queue (local onde fica as mensagens)
	$newResponse='';
	$mysqli = new mysqli("host", "usuario", "senha", "bancodedados");
	
	try {
		//Faz uma consulta no SQS que retorna uma mensagem
		$countMessage = $client->getQueueAttributes(array(
		    'QueueUrl' => $queueUrl,
		    'AttributeNames' => array('ApproximateNumberOfMessages'),
		));
		//Aqui eu consulta a quantidade de mensagens no SQS e crio um laço de repetição para ler estas mensagens.
		for($i=0; $i<$countMessage['Attributes']['ApproximateNumberOfMessages']; $i++){
			$result = $client->receiveMessage(array(
		        'AttributeNames' => ['SentTimestamp'],
		        'MaxNumberOfMessages' => 1,
		        'MessageAttributeNames' => ['All'],
		        'QueueUrl' => $queueUrl, 
		        'WaitTimeSeconds' => 0,
		    ));
		    //Verifica se realmente foi retornado uma mensagem
		    if (count($result->get('Messages')) > 0) {
		    	$mysqli->query("INSERT INTO SQS (json) VALUES('{$result->get('Messages')[0]['Body']}')");
		        //Apaga a mensagem que foi lido do SQS
		        $result = $client->deleteMessage([
		            'QueueUrl' => $queueUrl, 
		            'ReceiptHandle' => $result->get('Messages')[0]['ReceiptHandle'] 
		        ]);
		    } else {
		        echo "Não há mensagens por aqui. \n";
		    }	
		}
	    
	} catch (AwsException $e) {
       	error_log($e->getMessage());
	}
	$mysqli->close();
});
$app->run();