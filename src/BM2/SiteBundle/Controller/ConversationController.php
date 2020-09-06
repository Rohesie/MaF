<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Conversation;
use BM2\SiteBundle\Entity\Conversationpermission;
use BM2\SiteBundle\Entity\Message;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Form\AddParticipantType;
use BM2\SiteBundle\Form\MessageReplyType;
use BM2\SiteBundle\Form\NewConversationType;
use BM2\SiteBundle\Form\RecentReplyType;

use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @Route("/conv")
 */
class ConversationController extends Controller {

        /**
	  * @Route("/", name="maf_convs")
	  */
	public function indexAction() {
                $char = $this->get('dispatcher')->gateway('conversationListTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }
		$convs = $this->get('conversation_manager')->getConversations($char);

		return $this->render('Conversation/index.html.twig', [
			'conversations' => $convs,
			'char' => $char,
		]);
	}

	/**
	  * @Route("/summary", name="maf_conv_summary")
	  */
	public function summaryAction() {
                $char = $this->get('dispatcher')->gateway('conversationSummaryTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$unread = $this->get('conversation_manager')->getUnreadConvPermissions($char); #ArrayCollection
		$active = $this->get('conversation_manager')->getActiveConvPermissions($char); #ArrayCollection
		$total = $this->get('conversation_manager')->getConversationsCount($char); #Integer

		$new = ['messages' => 0, 'conversations' => 0];
		foreach ($unread as $perm) {
			$new['messages'] += $perm->getUnread();
			$new['conversations']++;
		}

		return $this->render('Conversation/summary.html.twig', [
			'active' => $active->count(),
			'total' => $total,
			'new' => $new,
			'flagged' => 0,
			'unread' => $unread,
			'local_news' => $this->get('news_manager')->getLocalList($char)
		]);
	}

	/**
	  * @Route("/unread", name="maf_conv_unread")
	  */
	public function unreadAction() {
                $char = $this->get('dispatcher')->gateway('conversationUnreadTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		return $this->render('Conversation/unread.html.twig', [
			'unread' => $this->get('conversation_manager')->getUnreadConvPermissions($char),
		]);
	}

	/**
	  * @Route("/contacts", name="maf_contacts")
	  */
	public function contactsAction() {
		return new Response("Feature not yet implemented. Try again later.");
                $char = $this->get('dispatcher')->gateway('conversationContactsTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($character);
                }

		return new Response(['contacts' => $this->get('conversation_manager')->getLegacyContacts($char)]);
	}

	/**
	  * @Route("/new", name="maf_conv_new")
	  * @Route("/new/r/{realm}", name="maf_conv_realm_new")
	  */
	public function newConversationAction(Request $request, Realm $realm=null) {
                $char = $this->get('dispatcher')->gateway('conversationNewTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		if ($realm && !$char->findRealms()->contains($realm)) {
			$realm = null;
		}

		if ($realm) {
			$contacts = null;
			$distance = null;
			$settlement = null;
		} else {
			if ($char->getAvailableEntourageOfType("herald")->isEmpty()) {
				$distance = $this->get('geography')->calculateInteractionDistance($char);
			} else {
				$distance = $this->get('geography')->calculateSpottingDistance($char);
			}
			$this->get('dispatcher')->setCharacter($char);
			$settlement = $this->get('dispatcher')->getActionableSettlement();
			$contacts = $this->get('conversation_manager')->getLegacyContacts($char);
		}

		$form = $this->createForm(new NewConversationType($contacts, $distance, $char, $settlement, $realm));

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			$recipients = new ArrayCollection;
			if (isset($data['owner'])) foreach ($data['owner'] as $rec) {
				if (!$recipients->contains($rec)) {
					$recipients->add($rec);
				}
			}
			if (isset($data['nearby'])) foreach ($data['nearby'] as $rec) {
				if (!$recipients->contains($rec)) {
					$recipients->add($rec);
				}
			}
			if (isset($data['captor'])) foreach ($data['captor'] as $rec) {
				if (!$recipients->contains($rec)) {
					$recipients->add($rec);
				}
			}
			if (isset($data['contacts'])) foreach ($data['contacts'] as $rec) {
				if (!$recipients->contains($rec)) {
					$recipients->add($rec);
				}
			}

			$conv = $this->get('conversation_manager')->newConversation($char, $recipients, $data['topic'], $data['type'], $data['content'], $realm);
			if ($conv === 'no recipients') {
				#TODO: Throw exception!
			}
			$url = $this->generateUrl('maf_conv_read', ['conv' => $conv->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
			$this->addFlash('notice', $this->get('translator')->trans('conversation.created', ["%url%"=>$url], 'conversations'));
			return $this->redirectToRoute('maf_conv_summary');
		}

		return $this->render('Conversation/new.html.twig', [
			'form' => $form->createView(),
			'realm' => $realm
		]);
	}

	/**
	  * @Route("/recent")
	  * @Route("/recent/")
	  * @Route("/recent/{window}", name="maf_conv_recent")
	  */
	public function recentAction(string $window='0') {
                $char = $this->get('dispatcher')->gateway('conversationRecentTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }
		$search = null;
		switch ($window) {
			case '0':
				$search = 'unread';
				break;
			case '1':
				$search = '-1 month';
				break;
			case '2':
				$search = '-14 days';
				break;
			case '3':
				$search = '-7 days';
				break;
			case '4':
				$search = '-3 days';
				break;
			case '5':
				$search = '-1 day';
				break;
			case '6':
				$search = '-12 hours';
				break;
			case '7':
				$search = '-2 months';
				break;
			default:
			case '8':
				$search = '-3 months';
				break;
		}
		if ($search == 'unread') {
			$all = $this->get('conversation_manager')->getAllUnreadMessages($char);
		} else {
			$all = $this->get('conversation_manager')->getAllRecentMessages($char, $search);
		}
		return $this->render('Conversation/recent.html.twig', [
			'messages' => $all,
			'string' => $window
		]);
	}

	/**
	  * @Route("/{conv}", name="maf_conv_read", requirements={"conv"="\d+"})
	  */
	public function readAction(Conversation $conv) {
                $char = $this->get('dispatcher')->gateway('conversationSingleTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$em = $this->getDoctrine()->getManager();
		$messages = $conv->findMessages($char);
		$perms = $conv->findCharPermissions($char);
		$lastPerm = $perms->last();
		$unread = $lastPerm->getUnread();
		$total = $messages->count();

		if ($unread) {
			$lastPerm->setUnread(0);
			$i = 0;
			foreach ($messages as $m) {
				$i++;
				if ($i == $total - $unread) {
					$last = $m->getSent();
					break;
				}
			}
		} else {
			$unread = 0;
			$last = NULL;
		}
		if ($lastPerm->getActive()) {
			$lastPerm->setLastAccess(new \DateTime('now'));
		}
		$em->flush();

		#Find the timestamp of the last read message.

		$veryold = new \DateTime('now');
		$veryold->sub(new \DateInterval("P30D")); // TODO: make this user-configurable

		return $this->render('Conversation/conversation.html.twig', [
			'conversation' => $conv,
			'messages' => $messages,
			'total' => $total,
			'unread' => $unread,
			'veryold' => $veryold,
			'last' => $last,
			'active'=> $lastPerm->getActive(),
		]);
	}

	/**
	  * @Route("/{conv}/participants", name="maf_conv_participants", requirements={"conv"="\d+"})
	  */
	public function participantsAction(Conversation $conv) {
                $char = $this->get('dispatcher')->gateway('conversationManageTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$perms = $conv->findRelevantPermissions($char); #Get what permissions we're aware of.

		$manager = false;
		$owner = false;
		$form = null;
		if (!$conv->getRealm()) {
			if ($me = $conv->findActiveCharpermission($char)) {
				$manager = $me->getManager();
				$owner = $me->getOwner();
			}
		}

		return $this->render('Conversation/participants.html.twig', [
			'conv' =>$conv,
			'perms'=>$perms,
			'manager'=>$manager,
			'owner'=>$owner,
			'active'=>$me,
			'me'=>$char,
		]);
	}

	/**
	  * @Route("/{conv}/add", name="maf_conv_add", requirements={"conv"="\d+"})
	  */
	public function addParticipantsAction(Request $request, Conversation $conv) {
                $char = $this->get('dispatcher')->gateway('conversationAddTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		# Dispatcher means we already know this user is either a manager or an owner, thus, they have add rights.
		$perms = $conv->findRelevantPermissions($char);
		$contacts = $this->get('conversation_manager')->getLegacyContacts($char);
		foreach ($perms as $perm) {
			if ($perm->getCharacter() && in_array($perm->getCharacter(), $contacts)) {
				unset($contacts[$perm->getCharacter()->getId()]); #Remove people who already have permissions.
			}
		}
		$form = $this->createForm(new AddParticipantType($contacts));

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();
			$current = $conv->findActivePermissions();
			$now = new \DateTime("now");
			$added = new ArrayCollection();
			foreach($data['contacts'] as $new) {
				# Double check we can actually add this person.
				if (in_array($new, $contacts)) {
					# Also check that we aren't adding a duplicate permission.
					if (!$current->contains($new) && !$added->contains($new)) {
						$perm = new ConversationPermission();
						$em->persist($perm);
						$perm->setConversation($conv);
						$perm->setCharacter($new);
						$perm->setStartTime($now);
						$perm->setActive(true);
						$perm->setUnread(0);
						$perm->setManager(false);
						$perm->setOwner(false);
						$added->add($new);
					}
				}
			}
			$message = $this->get('conversation_manager')->newSystemMessage($conv, 'newperms', $added, $char, false);
			$em->flush();
			return new RedirectResponse($this->generateUrl('maf_conv_read', ['conv' => $conv->getId()]).'#'.$message->getId());
		}
		return $this->render('Conversation/add.html.twig', [
			'conv'=>$conv,
			'perms'=>$perms,
			'form'=>$form->createView(),
		]);
	}

	/**
	  * @Route("/{conv}/change/{perm}/{var}", name="maf_conv_change", requirements={"conv"="\d+", "perm"="\d+", "var"="\d+"})
	  */
	public function changePermissionAction(Conversation $conv, ConversationPermission $perm, $var) {
                $char = $this->get('dispatcher')->gateway('conversationChangeTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		if ($me = $conv->findActiveCharPermission($char)) {
			$em = $this->getDoctrine()->getManager();
			$flush = false;
			$change = 'permission.invalidrequest';
			$now = new \DateTime("now");
			if ($me->getOwner()) {
				if (!$perm->getManager()) {
					if ($var == 0) {
						$perm->setActive(false);
						$perm->setEndTime($now);
						$change = 'permission.demoted.removed';
						$flush = true;
						# Yes, newSystemMessage expects a collection. So we cast the char into an array that is then cast into a collection. It works.
						$message = $this->get('conversation_manager')->newSystemMessage($conv, 'removal', new ArrayCollection([$perm->getCharacter()]), $char, false);

					} elseif ($var == 1) {
						$perm->setManager(true);
						$flush = true;
						$change = 'permission.promoted.manager';
					}
				} elseif ($perm->getOwner()) {
					if ($var == 0) {
						$perm->setOwner(false);
						$flush = true;
						$change = 'permission.demoted.manager';
					} elseif ($var === 1) {
						$change = 'permission.promoted.invalid';
					}
				} else {
					if ($var == 0) {
						$perm->setManager(false);
						$flush = true;
						$change = 'permission.demoted.user';
					} elseif ($var == 1) {
						$perm->setOwner(true);
						$flush = true;
						$change = 'permission.promoted.owner';
					}
				}
			} elseif ($me->getManager()) {
				if ($perm->getOwner() || $perm->getManager()) {
					$change = 'permission.invalidrequest';
				} else {
					if ($var === 0) {
						$perm->setActive(false);
						$perm->setEndTime($now);
						$flush = true;
						$change = 'permission.demoted.removed';
						$message = $this->get('conversation_manager')->newSystemMessage($conv, 'removal', new ArrayCollection([$perm->getCharacter()]), $char, false);
					} elseif ($var === 1) {
						$change = 'permission.nopromoteright';
					}
				}
			}
			if ($flush) {
				$em->flush();
			}
		}

		$this->addFlash('notice', $this->get('translator')->trans($change, ["%name%"=>$perm->getCharacter()->getName()], 'conversations'));

		return $this->redirectToRoute('maf_conv_participants', ['conv'=>$conv->getId()]);
	}

	/**
	  * @Route("/{conv}/leave", name="maf_conv_leave", requirements={"conv"="\d+"})
	  */
	public function leaveAction(Conversation $conv) {
                $char = $this->get('dispatcher')->gateway('conversationLeaveTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		if ($perm = $conv->findActiveCharPermission($char)) {
			$em = $this->getDoctrine()->getManager();
			$perm->setActive(false);
			$perm->setEndTime(new \DateTime("now"));
			$message = $this->get('conversation_manager')->newSystemMessage($conv, 'left', null, $char, false);
			if ($perm->getOwner()) {
				$perm->setOwner(false);
				$perm->setManager(false);
				$this->get('conversation_manager')->findNewOwner($conv, $char, false);
			} elseif ($perm->getManager()) {
				$perm->setManager(false);
			}
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('conversation.left', ["%name%"=>$perm->getConversation()->getTopic()], 'conversations'));

			return new RedirectResponse($this->generateUrl('maf_conv_read', ['conv' => $conv->getId()]).'#'.$message->getId());
		}

		$this->addFlash('notice', $this->get('translator')->trans('conversation.left', ["%name%"=>$perm->getConversation()->getTopic()], 'conversations'));
		return new RedirectResponse($this->generateUrl('maf_conv_read', ['conv' => $conv->getId()]));

	}

	/**
	  * @Route("/{conv}/remove", name="maf_conv_remove", requirements={"conv"="\d+"})
	  */
	public function removeAction(Conversation $conv) {
                $char = $this->get('dispatcher')->gateway('conversationRemoveTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		if ($perms = $conv->findCharPermissions($char)) {
			$em = $this->getDoctrine()->getManager();
			$wasOwner = false;
			foreach ($perms as $perm) {
				if ($perm->getOwner()) {
					$wasOwner = true;
				}
				$em->remove($perm);
			}
			if ($wasOwner) {
				$this->get('conversation_manager')->findNewOwner($conv, $char, false);
			}
			$em->flush();
			$this->get('conversation_manager')->pruneConversation($conv);
			$this->addFlash('notice', $this->get('translator')->trans('conversation.removed', ["%name%"=>$conv->getTopic()], 'conversations'));
		} else {
			$this->addFlash('notice', $this->get('translator')->trans('conversation.badremoved', ["%id%"=>$conv->getId()], 'conversations'));
		}

		return new RedirectResponse($this->generateUrl('maf_conv_summary'));
	}

	/**
	  * @Route("/{conv}/reply", name="maf_conv_reply", requirements={"conv"="\d+"})
	  */
	public function replyAction(Conversation $conv, Request $request) {
                $char = $this->get('dispatcher')->gateway('conversationReplyTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$form = $this->createForm(new MessageReplyType());

		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();
			$replyTo = $data['reply_to'];

			$message = $this->get('conversation_manager')->writeMessage($conv, $replyTo, $char, $data['content'], $data['type']);

			return new RedirectResponse($this->generateUrl('maf_conv_read', ['conv' => $conv->getId()]).'#'.$message->getId());
		}

		return $this->render('Conversation/reply.html.twig', [
			'form' => $form->createView()
		]);
	}


	/**
	  * @Route("/recent/reply/{msg}", requirements={"msg"="\d+"})
	  * @Route("/recent/reply/{msg}/", requirements={"msg"="\d+"})
	  * @Route("/recent/reply/{msg}/{string}", name="maf_conv_recent_reply", requirements={"msg"="\d+"})
	  */
	public function replyRecentAction(Message $msg, Request $request, string $string='0') {
		$conv = $msg->getConversation();
                $char = $this->get('dispatcher')->gateway('conversationReplyTest', false, true, false, $conv); # Reuse is deliberate!
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$form = $this->createForm(new RecentReplyType());

		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();

			$message = $this->get('conversation_manager')->writeMessage($conv, $msg, $char, $data['content'], $data['type']);

			return new RedirectResponse($this->generateUrl('maf_conv_recent', ['window' => $string]).'#'.$message->getId());
		}

		return $this->render('Conversation/recentreply.html.twig', [
			'message' => $msg,
			'form' => $form->createView()
		]);
	}
}
