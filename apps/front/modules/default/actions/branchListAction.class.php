<?php

/**
 * repository actions.
 *
 * @package    crew
 * @subpackage repository
 * @author     Your name here
 */
class branchListAction extends sfAction
{
  /**
   * @param sfWebRequest $request
   * @return void
   */
  public function execute($request)
  {
    $repository = RepositoryPeer::retrieveByPK($request->getParameter('repository'));
    $this->forward404Unless($repository, "Repository Not Found");

    $this->repository = $repository;
    $branches = BranchQuery::create()
      ->filterByIsBlacklisted(false)
      ->filterByRepositoryId($this->repository->getId())
      ->find()
    ;
    $this->branches = array();
    foreach ($branches as $branch)
    {
      $addedFilesCount = FileQuery::create()->filterByBranchId($branch->getId())->filterByState(FilePeer::ADDED)->count();
      $modifiedFilesCount = FileQuery::create()->filterByBranchId($branch->getId())->filterByState(FilePeer::MODIFIED)->count();
      $deletedFilesCount = FileQuery::create()->filterByBranchId($branch->getId())->filterByState(FilePeer::DELETED)->count();

      $this->branches[] = array_merge($branch->toArray(), array(
        'total' => $addedFilesCount + $modifiedFilesCount + $deletedFilesCount,
        'added' => $addedFilesCount,
        'modified' => $modifiedFilesCount,
        'deleted' => $deletedFilesCount
      ));
    }

    $this->statusActions = StatusActionQuery::create()
      ->orderByCreatedAt(\Criteria::DESC)
      ->filterByRepositoryId($repository->getId())
      ->limit(25)
      ->find()
    ;

    $this->commentBoards = $this->getCommentBoards($repository->getId());
  }

  private function getCommentBoards($repositoryId)
  {
    $commentBoards = array();

    $branchComments = BranchCommentQuery::create()
      ->orderByCreatedAt(Criteria::DESC)
      ->useBranchQuery()
        ->filterByRepositoryId($repositoryId)
      ->endUse()
      ->find()
    ;

    foreach ($branchComments as $branchComment)
    {
      $commentBoards[$branchComment->getCreatedAt('YmdHisu')] = array(
        'User' => $branchComment->getAuthorName(),
        'Message' => sprintf('%s <strong>on branch %s</strong>', stringUtils::shorten($branchComment->getValue(), 60), $branchComment->getBranch()->__toString()),
        'CreatedAt' => $branchComment->getCreatedAt('d/m/Y H:i:s')
      );
    }

    $FileComments = FileCommentQuery::create()
      ->orderByCreatedAt(Criteria::DESC)
      ->useFileQuery()
        ->useBranchQuery()
        ->filterByRepositoryId($repositoryId)
        ->endUse()
      ->endUse()
      ->find()
    ;

    foreach ($FileComments as $FileComment)
    {
      $commentBoards[$FileComment->getCreatedAt('YmdHisu')] = array(
        'User' => $FileComment->getAuthorName(),
        'Message' => sprintf('%s <strong>on file %s</strong>', stringUtils::shorten($FileComment->getValue(), 60), $FileComment->getFile()->getFilename()),
        'CreatedAt' => $FileComment->getCreatedAt('d/m/Y H:i:s')
      );
    }

    $LineComments = LineCommentQuery::create()
      ->orderByCreatedAt(Criteria::DESC)
      ->useFileQuery()
        ->useBranchQuery()
        ->filterByRepositoryId($repositoryId)
        ->endUse()
      ->endUse()
      ->find()
    ;

    foreach ($LineComments as $LineComment)
    {
      $commentBoards[$LineComment->getCreatedAt('YmdHisu')] = array(
        'User' => $LineComment->getAuthorName(),
        'Message' => sprintf('%s <strong>on line %s of file %s</strong>', stringUtils::shorten($LineComment->getValue(), 60), $LineComment->getLine(), $LineComment->getFile()->getFilename()),
        'CreatedAt' => $LineComment->getCreatedAt('d/m/Y H:i:s')
      );
    }

    krsort($commentBoards);

    return $commentBoards;
  }
}
