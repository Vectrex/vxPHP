<?php
namespace vxPHP\Observer;

interface ListenerInterface {
	public function update(SubjectInterface $subject);
}
