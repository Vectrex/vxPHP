<?php
namespace vxPHP\Observer;

interface ListenerInterface {

	public function update(SubjectInterface $subject);

	public function setParameters(array $parameters = NULL);

}
