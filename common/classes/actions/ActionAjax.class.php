<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */

/**
 * Экшен обработки ajax запросов
 * Ответ отдает в JSON фомате
 *
 * @package actions
 * @since   1.0
 */
class ActionAjax extends Action {
    /**
     * Текущий пользователь
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent = null;

    /**
     * Инициализация
     */
    public function Init() {

        // * Устанавливаем формат ответа
        $this->Viewer_SetResponseAjax('json');

        // * Получаем текущего пользователя
        $this->oUserCurrent = $this->User_GetUserCurrent();
    }

    /**
     * Регистрация евентов
     */
    protected function RegisterEvent() {

        $this->AddEventPreg('/^vote$/i', '/^comment$/', 'EventVoteComment');
        $this->AddEventPreg('/^vote$/i', '/^topic$/', 'EventVoteTopic');
        //$this->AddEventPreg('/^vote$/i', '/^blog$/', 'EventVoteBlog');
        $this->AddEventPreg('/^vote$/i', '/^user$/', 'EventVoteUser');
        $this->AddEventPreg('/^vote$/i', '/^question$/', 'EventVoteQuestion');

        $this->AddEventPreg('/^favourite$/i', '/^save-tags/', 'EventFavouriteSaveTags');
        $this->AddEventPreg('/^favourite$/i', '/^topic$/', 'EventFavouriteTopic');
        $this->AddEventPreg('/^favourite$/i', '/^comment$/', 'EventFavouriteComment');
        $this->AddEventPreg('/^favourite$/i', '/^talk$/', 'EventFavouriteTalk');

        $this->AddEventPreg('/^stream$/i', '/^comment$/', 'EventStreamComment');
        $this->AddEventPreg('/^stream$/i', '/^topic$/', 'EventStreamTopic');

        $this->AddEventPreg('/^blogs$/i', '/^top$/', 'EventBlogsTop');
        $this->AddEventPreg('/^blogs$/i', '/^self$/', 'EventBlogsSelf');
        $this->AddEventPreg('/^blogs$/i', '/^join$/', 'EventBlogsJoin');

        $this->AddEventPreg('/^preview$/i', '/^text$/', 'EventPreviewText');
        $this->AddEventPreg('/^preview$/i', '/^topic/', 'EventPreviewTopic');

        $this->AddEventPreg('/^upload$/i', '/^image$/', 'EventUploadImage');

        $this->AddEventPreg('/^autocompleter$/i', '/^tag$/', 'EventAutocompleterTag');
        $this->AddEventPreg('/^autocompleter$/i', '/^user$/', 'EventAutocompleterUser');

        $this->AddEventPreg('/^comment$/i', '/^delete$/', 'EventCommentDelete');

        $this->AddEventPreg('/^geo/i', '/^get/', '/^regions$/', 'EventGeoGetRegions');
        $this->AddEventPreg('/^geo/i', '/^get/', '/^cities/', 'EventGeoGetCities');

        $this->AddEventPreg('/^infobox/i', '/^info/', '/^blog/', 'EventInfoboxInfoBlog');

        $this->AddEvent('fetch', 'EventFetch');
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Вывод информации о блоге
     */
    protected function EventInfoboxInfoBlog() {

        // * Если блог существует и он не персональный
        if (!is_string(getRequest('iBlogId'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'));
            return;
        }

        if (!($oBlog = $this->Blog_GetBlogById(getRequest('iBlogId'))) /* || $oBlog->getType()=='personal'*/) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'));
            return;
        }

        // * Получаем локальный вьюер для рендеринга шаблона
        $oViewer = $this->Viewer_GetLocalViewer();

        $oViewer->Assign('oBlog', $oBlog);
        if (!$oBlog->getBlogType()->IsPrivate() || $oBlog->getUserIsJoin()) {
            // * Получаем последний топик
            $aResult = $this->Topic_GetTopicsByFilter(array('blog_id' => $oBlog->getId(), 'topic_publish' => 1), 1, 1);
            $oViewer->Assign('oTopicLast', reset($aResult['collection']));
        }
        $oViewer->Assign('oUserCurrent', $this->oUserCurrent);

        // * Устанавливаем переменные для ajax ответа
        $this->Viewer_AssignAjax('sText', $oViewer->Fetch('infobox.info.blog.tpl'));
    }

    /**
     * Получение списка регионов по стране
     */
    protected function EventGeoGetRegions() {

        $iCountryId = getRequestStr('country');
        $iLimit = 200;
        if (is_numeric(getRequest('limit')) && getRequest('limit') > 0) {
            $iLimit = getRequest('limit');
        }

        // * Находим страну
        if (!($oCountry = $this->Geo_GetGeoObject('country', $iCountryId))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'));
            return;
        }

        // * Получаем список регионов
        $aResult = $this->Geo_GetRegions(array('country_id' => $oCountry->getId()), array('sort' => 'asc'), 1, $iLimit);
        $aRegions = array();
        foreach ($aResult['collection'] as $oObject) {
            $aRegions[] = array(
                'id'   => $oObject->getId(),
                'name' => $oObject->getName(),
            );
        }

        // * Устанавливаем переменные для ajax ответа
        $this->Viewer_AssignAjax('aRegions', $aRegions);
    }

    /**
     * Получение списка городов по региону
     */
    protected function EventGeoGetCities() {

        $iRegionId = getRequestStr('region');
        $iLimit = 500;
        if (is_numeric(getRequest('limit')) && getRequest('limit') > 0) {
            $iLimit = getRequest('limit');
        }

        // * Находим регион
        if (!($oRegion = $this->Geo_GetGeoObject('region', $iRegionId))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'));
            return;
        }

        // * Получаем города
        $aResult = $this->Geo_GetCities(array('region_id' => $oRegion->getId()), array('sort' => 'asc'), 1, $iLimit);
        $aCities = array();
        foreach ($aResult['collection'] as $oObject) {
            $aCities[] = array(
                'id'   => $oObject->getId(),
                'name' => $oObject->getName(),
            );
        }

        // * Устанавливаем переменные для ajax ответа
        $this->Viewer_AssignAjax('aCities', $aCities);
    }

    /**
     * Голосование за комментарий
     *
     */
    protected function EventVoteComment() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }

        // * Комментарий существует?
        if (!($oComment = $this->Comment_GetCommentById(getRequestStr('idComment', null, 'post')))) {
            $this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_noexists'), $this->Lang_Get('error'));
            return;
        }

        // * Голосует автор комментария?
        if ($oComment->getUserId() == $this->oUserCurrent->getId()) {
            $this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_self'), $this->Lang_Get('attention'));
            return;
        }

        // * Пользователь уже голосовал?
        if ($oTopicCommentVote = $this->Vote_GetVote($oComment->getId(), 'comment', $this->oUserCurrent->getId())) {
            $this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_already'), $this->Lang_Get('attention'));
            return;
        }

        // * Время голосования истекло?
        if (strtotime($oComment->getDate()) <= time() - Config::Get('acl.vote.comment.limit_time')) {
            $this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_time'), $this->Lang_Get('attention'));
            return;
        }

        // * Пользователь имеет право голоса?
        if (!$this->ACL_CanVoteComment($this->oUserCurrent, $oComment)) {
            $this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_acl'), $this->Lang_Get('attention'));
            return;
        }

        // * Как именно голосует пользователь
        $iValue = getRequestStr('value', null, 'post');
        if (!in_array($iValue, array('1', '-1'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_value'), $this->Lang_Get('attention'));
            return;
        }

        // * Голосуем
        $oTopicCommentVote = Engine::GetEntity('Vote');
        $oTopicCommentVote->setTargetId($oComment->getId());
        $oTopicCommentVote->setTargetType('comment');
        $oTopicCommentVote->setVoterId($this->oUserCurrent->getId());
        $oTopicCommentVote->setDirection($iValue);
        $oTopicCommentVote->setDate(F::Now());
        $iVal = (float)$this->Rating_VoteComment($this->oUserCurrent, $oComment, $iValue);
        $oTopicCommentVote->setValue($iVal);

        $oComment->setCountVote($oComment->getCountVote() + 1);
        if ($this->Vote_AddVote($oTopicCommentVote) && $this->Comment_UpdateComment($oComment)) {
            $this->Message_AddNoticeSingle($this->Lang_Get('comment_vote_ok'), $this->Lang_Get('attention'));
            $this->Viewer_AssignAjax('iRating', $oComment->getRating());
            /**
             * Добавляем событие в ленту
             */
            $this->Stream_Write($oTopicCommentVote->getVoterId(), 'vote_comment', $oComment->getId());
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error'), $this->Lang_Get('error'));
            return;
        }
    }

    /**
     * Голосование за топик
     *
     */
    protected function EventVoteTopic() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }

        // * Топик существует?
        if (!($oTopic = $this->Topic_GetTopicById(getRequestStr('idTopic', null, 'post')))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

        // * Голосует автор топика?
        if ($oTopic->getUserId() == $this->oUserCurrent->getId()) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_vote_error_self'), $this->Lang_Get('attention'));
            return;
        }

        // * Пользователь уже голосовал?
        if ($oTopicVote = $this->Vote_GetVote($oTopic->getId(), 'topic', $this->oUserCurrent->getId())) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_vote_error_already'), $this->Lang_Get('attention'));
            return;
        }

        // * Время голосования истекло?
        if (strtotime($oTopic->getDateAdd()) <= time() - Config::Get('acl.vote.topic.limit_time')) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_vote_error_time'), $this->Lang_Get('attention'));
            return;
        }

        // * Как проголосовал пользователь
        $iValue = getRequestStr('value', null, 'post');
        if (!in_array($iValue, array('1', '-1', '0'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('attention'));
            return;
        }

        // * Права на голосование
        if (!$this->ACL_CanVoteTopic($this->oUserCurrent, $oTopic) && $iValue) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_vote_error_acl'), $this->Lang_Get('attention'));
            return;
        }

        // * Голосуем
        $oTopicVote = Engine::GetEntity('Vote');
        $oTopicVote->setTargetId($oTopic->getId());
        $oTopicVote->setTargetType('topic');
        $oTopicVote->setVoterId($this->oUserCurrent->getId());
        $oTopicVote->setDirection($iValue);
        $oTopicVote->setDate(F::Now());
        $iVal = 0;
        if ($iValue != 0) {
            $iVal = (float)$this->Rating_VoteTopic($this->oUserCurrent, $oTopic, $iValue);
        }
        $oTopicVote->setValue($iVal);
        $oTopic->setCountVote($oTopic->getCountVote() + 1);
        if ($iValue == 1) {
            $oTopic->setCountVoteUp($oTopic->getCountVoteUp() + 1);
        } elseif ($iValue == -1) {
            $oTopic->setCountVoteDown($oTopic->getCountVoteDown() + 1);
        } elseif ($iValue == 0) {
            $oTopic->setCountVoteAbstain($oTopic->getCountVoteAbstain() + 1);
        }
        if ($this->Vote_AddVote($oTopicVote) && $this->Topic_UpdateTopic($oTopic)) {
            if ($iValue) {
                $this->Message_AddNoticeSingle($this->Lang_Get('topic_vote_ok'), $this->Lang_Get('attention'));
            } else {
                $this->Message_AddNoticeSingle($this->Lang_Get('topic_vote_ok_abstain'), $this->Lang_Get('attention'));
            }
            $this->Viewer_AssignAjax('iRating', $oTopic->getRating());
            /**
             * Добавляем событие в ленту
             */
            $this->Stream_write($oTopicVote->getVoterId(), 'vote_topic', $oTopic->getId());
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
    }

    /**
     * Голосование за блог
     *
     */
    protected function EventVoteBlog() {
        /**
         * Пользователь авторизован?
         */
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }
        /**
         * Блог существует?
         */
        if (!($oBlog = $this->Blog_GetBlogById(getRequestStr('idBlog', null, 'post')))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
        /**
         * Голосует за свой блог?
         */
        if ($oBlog->getOwnerId() == $this->oUserCurrent->getId()) {
            $this->Message_AddErrorSingle($this->Lang_Get('blog_vote_error_self'), $this->Lang_Get('attention'));
            return;
        }
        /**
         * Уже голосовал?
         */
        if ($oBlogVote = $this->Vote_GetVote($oBlog->getId(), 'blog', $this->oUserCurrent->getId())) {
            $this->Message_AddErrorSingle($this->Lang_Get('blog_vote_error_already'), $this->Lang_Get('attention'));
            return;
        }
        /**
         * Имеет право на голосование?
         */
        switch ($this->ACL_CanVoteBlog($this->oUserCurrent, $oBlog)) {
            case ModuleACL::CAN_VOTE_BLOG_TRUE:
                $iValue = getRequestStr('value', null, 'post');
                if (in_array($iValue, array('1', '-1'))) {
                    $oBlogVote = Engine::GetEntity('Vote');
                    $oBlogVote->setTargetId($oBlog->getId());
                    $oBlogVote->setTargetType('blog');
                    $oBlogVote->setVoterId($this->oUserCurrent->getId());
                    $oBlogVote->setDirection($iValue);
                    $oBlogVote->setDate(F::Now());
                    $iVal = (float)$this->Rating_VoteBlog($this->oUserCurrent, $oBlog, $iValue);
                    $oBlogVote->setValue($iVal);
                    $oBlog->setCountVote($oBlog->getCountVote() + 1);
                    if ($this->Vote_AddVote($oBlogVote) && $this->Blog_UpdateBlog($oBlog)) {
                        $this->Viewer_AssignAjax('iCountVote', $oBlog->getCountVote());
                        $this->Viewer_AssignAjax('iRating', $oBlog->getRating());
                        $this->Message_AddNoticeSingle($this->Lang_Get('blog_vote_ok'), $this->Lang_Get('attention'));
                        /**
                         * Добавляем событие в ленту
                         */
                        $this->Stream_write($oBlogVote->getVoterId(), 'vote_blog', $oBlog->getId());
                    } else {
                        $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('attention'));
                        return;
                    }
                } else {
                    $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('attention'));
                    return;
                }
                break;
            case ModuleACL::CAN_VOTE_BLOG_ERROR_CLOSE:
                $this->Message_AddErrorSingle($this->Lang_Get('blog_vote_error_close'), $this->Lang_Get('attention'));
                return;
                break;

            default:
            case ModuleACL::CAN_VOTE_BLOG_FALSE:
                $this->Message_AddErrorSingle($this->Lang_Get('blog_vote_error_acl'), $this->Lang_Get('attention'));
                return;
                break;
        }
    }

    /**
     * Голосование за пользователя
     *
     */
    protected function EventVoteUser() {
        /**
         * Пользователь авторизован?
         */
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }
        /**
         * Пользователь существует?
         */
        if (!($oUser = $this->User_GetUserById(getRequestStr('idUser', null, 'post')))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
        /**
         * Голосует за себя?
         */
        if ($oUser->getId() == $this->oUserCurrent->getId()) {
            $this->Message_AddErrorSingle($this->Lang_Get('user_vote_error_self'), $this->Lang_Get('attention'));
            return;
        }
        /**
         * Уже голосовал?
         */
        if ($oUserVote = $this->Vote_GetVote($oUser->getId(), 'user', $this->oUserCurrent->getId())) {
            $this->Message_AddErrorSingle($this->Lang_Get('user_vote_error_already'), $this->Lang_Get('attention'));
            return;
        }
        /**
         * Имеет право на голосование?
         */
        if (!$this->ACL_CanVoteUser($this->oUserCurrent, $oUser)) {
            $this->Message_AddErrorSingle($this->Lang_Get('user_vote_error_acl'), $this->Lang_Get('attention'));
            return;
        }
        /**
         * Как проголосовал
         */
        $iValue = getRequestStr('value', null, 'post');
        if (!in_array($iValue, array('1', '-1'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('attention'));
            return;
        }
        /**
         * Голосуем
         */
        $oUserVote = Engine::GetEntity('Vote');
        $oUserVote->setTargetId($oUser->getId());
        $oUserVote->setTargetType('user');
        $oUserVote->setVoterId($this->oUserCurrent->getId());
        $oUserVote->setDirection($iValue);
        $oUserVote->setDate(F::Now());
        $iVal = (float)$this->Rating_VoteUser($this->oUserCurrent, $oUser, $iValue);
        $oUserVote->setValue($iVal);
        //$oUser->setRating($oUser->getRating()+$iValue);
        $oUser->setCountVote($oUser->getCountVote() + 1);
        if ($this->Vote_AddVote($oUserVote) && $this->User_Update($oUser)) {
            $this->Message_AddNoticeSingle($this->Lang_Get('user_vote_ok'), $this->Lang_Get('attention'));
            $this->Viewer_AssignAjax('iRating', $oUser->getRating());
            $this->Viewer_AssignAjax('iSkill', $oUser->getSkill());
            $this->Viewer_AssignAjax('iCountVote', $oUser->getCountVote());
            /**
             * Добавляем событие в ленту
             */
            $this->Stream_write($oUserVote->getVoterId(), 'vote_user', $oUser->getId());
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
    }

    /**
     * Голосование за вариант ответа в опросе
     *
     */
    protected function EventVoteQuestion() {
        /**
         * Пользователь авторизован?
         */
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }
        /**
         * Параметры голосования
         */
        $idAnswer = getRequestStr('idAnswer', null, 'post');
        $idTopic = getRequestStr('idTopic', null, 'post');
        /**
         * Топик существует?
         */
        if (!($oTopic = $this->Topic_GetTopicById($idTopic))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
        /**
         *  У топика существует опрос?
         */
        if (!$oTopic->getQuestionAnswers()) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

        // * Уже голосовал?
        if ($oTopicQuestionVote = $this->Topic_GetTopicQuestionVote($oTopic->getId(), $this->oUserCurrent->getId())) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_question_vote_already'), $this->Lang_Get('error'));
            return;
        }

        // * Вариант ответа
        $aAnswer = $oTopic->getQuestionAnswers();
        if (!isset($aAnswer[$idAnswer]) && $idAnswer != -1) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

        if ($idAnswer == -1) {
            $oTopic->setQuestionCountVoteAbstain($oTopic->getQuestionCountVoteAbstain() + 1);
        } else {
            $oTopic->increaseQuestionAnswerVote($idAnswer);
        }
        $oTopic->setQuestionCountVote($oTopic->getQuestionCountVote() + 1);

        // * Голосуем(отвечаем на опрос)
        $oTopicQuestionVote = Engine::GetEntity('Topic_TopicQuestionVote');
        $oTopicQuestionVote->setTopicId($oTopic->getId());
        $oTopicQuestionVote->setVoterId($this->oUserCurrent->getId());
        $oTopicQuestionVote->setAnswer($idAnswer);

        if ($this->Topic_AddTopicQuestionVote($oTopicQuestionVote) && $this->Topic_updateTopic($oTopic)) {
            $this->Message_AddNoticeSingle($this->Lang_Get('topic_question_vote_ok'), $this->Lang_Get('attention'));
            $oViewer = $this->Viewer_GetLocalViewer();
            $oViewer->Assign('oTopic', $oTopic);
            $this->Viewer_AssignAjax('sText', $oViewer->Fetch('question_result.tpl'));
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
    }

    /**
     * Сохраняет теги для избранного
     *
     */
    protected function EventFavouriteSaveTags() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }

        // * Объект уже должен быть в избранном
        if ($oFavourite = $this->Favourite_GetFavourite(
            getRequestStr('target_id'), getRequestStr('target_type'), $this->oUserCurrent->getId()
        )) {
            // * Обрабатываем теги
            $aTags = explode(',', trim(getRequestStr('tags'), "\r\n\t\0\x0B ."));
            $aTagsNew = array();
            $aTagsNewLow = array();
            $aTagsReturn = array();
            foreach ($aTags as $sTag) {
                $sTag = trim($sTag);
                if (F::CheckVal($sTag, 'text', 2, 50) && !in_array(mb_strtolower($sTag, 'UTF-8'), $aTagsNewLow)) {
                    $sTagEsc = htmlspecialchars($sTag);
                    $aTagsNew[] = $sTagEsc;
                    $aTagsReturn[] = array(
                        'tag' => $sTagEsc,
                        'url' =>
                        $this->oUserCurrent->getUserWebPath() . 'favourites/' . $oFavourite->getTargetType() . 's/tag/'
                            . $sTagEsc . '/', // костыль для URL с множественным числом
                    );
                    $aTagsNewLow[] = mb_strtolower($sTag, 'UTF-8');
                }
            }
            if (!count($aTagsNew)) {
                $oFavourite->setTags('');
            } else {
                $oFavourite->setTags(join(',', $aTagsNew));
            }
            $this->Viewer_AssignAjax('aTags', $aTagsReturn);
            $this->Favourite_UpdateFavourite($oFavourite);
            return;
        }
        $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
    }

    /**
     * Обработка избранного - топик
     *
     */
    protected function EventFavouriteTopic() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }

        // * Можно только добавить или удалить из избранного
        $iType = getRequestStr('type', null, 'post');
        if (!in_array($iType, array('1', '0'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

        // * Топик существует?
        if (!($oTopic = $this->Topic_GetTopicById(getRequestStr('idTopic', null, 'post')))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

        // * Пропускаем топик из черновиков
        if (!$oTopic->getPublish()) {
            $this->Message_AddErrorSingle($this->Lang_Get('error_favorite_topic_is_draft'), $this->Lang_Get('error'));
            return;
        }

        // * Топик уже в избранном?
        $oFavouriteTopic = $this->Topic_GetFavouriteTopic($oTopic->getId(), $this->oUserCurrent->getId());
        if (!$oFavouriteTopic && $iType) {
            $oFavouriteTopicNew = Engine::GetEntity(
                'Favourite',
                array(
                     'target_id'      => $oTopic->getId(),
                     'user_id'        => $this->oUserCurrent->getId(),
                     'target_type'    => 'topic',
                     'target_publish' => $oTopic->getPublish()
                )
            );
            $oTopic->setCountFavourite($oTopic->getCountFavourite() + 1);
            if ($this->Topic_AddFavouriteTopic($oFavouriteTopicNew) && $this->Topic_UpdateTopic($oTopic)) {
                $this->Message_AddNoticeSingle($this->Lang_Get('topic_favourite_add_ok'), $this->Lang_Get('attention'));
                $this->Viewer_AssignAjax('bState', true);
                $this->Viewer_AssignAjax('iCount', $oTopic->getCountFavourite());
            } else {
                $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                return;
            }
        }
        if (!$oFavouriteTopic && !$iType) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_favourite_add_no'), $this->Lang_Get('error'));
            return;
        }
        if ($oFavouriteTopic && $iType) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_favourite_add_already'), $this->Lang_Get('error'));
            return;
        }
        if ($oFavouriteTopic && !$iType) {
            $oTopic->setCountFavourite($oTopic->getCountFavourite() - 1);
            if ($this->Topic_DeleteFavouriteTopic($oFavouriteTopic) && $this->Topic_UpdateTopic($oTopic)) {
                $this->Message_AddNoticeSingle($this->Lang_Get('topic_favourite_del_ok'), $this->Lang_Get('attention'));
                $this->Viewer_AssignAjax('bState', false);
                $this->Viewer_AssignAjax('iCount', $oTopic->getCountFavourite());
            } else {
                $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                return;
            }
        }
    }

    /**
     * Обработка избранного - комментарий
     *
     */
    protected function EventFavouriteComment() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }

        // * Можно только добавить или удалить из избранного
        $iType = getRequestStr('type', null, 'post');
        if (!in_array($iType, array('1', '0'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

        // * Комментарий существует?
        if (!($oComment = $this->Comment_GetCommentById(getRequestStr('idComment', null, 'post')))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
        // * Комментарий уже в избранном?
        $oFavouriteComment = $this->Comment_GetFavouriteComment($oComment->getId(), $this->oUserCurrent->getId());
        if (!$oFavouriteComment && $iType) {
            $oFavouriteCommentNew = Engine::GetEntity(
                'Favourite',
                array(
                     'target_id'      => $oComment->getId(),
                     'target_type'    => 'comment',
                     'user_id'        => $this->oUserCurrent->getId(),
                     'target_publish' => $oComment->getPublish()
                )
            );
            $oComment->setCountFavourite($oComment->getCountFavourite() + 1);
            if ($this->Comment_AddFavouriteComment($oFavouriteCommentNew) && $this->Comment_UpdateComment($oComment)) {
                $this->Message_AddNoticeSingle(
                    $this->Lang_Get('comment_favourite_add_ok'), $this->Lang_Get('attention')
                );
                $this->Viewer_AssignAjax('bState', true);
                $this->Viewer_AssignAjax('iCount', $oComment->getCountFavourite());
            } else {
                $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                return;
            }
        }
        if (!$oFavouriteComment && !$iType) {
            $this->Message_AddErrorSingle($this->Lang_Get('comment_favourite_add_no'), $this->Lang_Get('error'));
            return;
        }
        if ($oFavouriteComment && $iType) {
            $this->Message_AddErrorSingle($this->Lang_Get('comment_favourite_add_already'), $this->Lang_Get('error'));
            return;
        }
        if ($oFavouriteComment && !$iType) {
            $oComment->setCountFavourite($oComment->getCountFavourite() - 1);
            if ($this->Comment_DeleteFavouriteComment($oFavouriteComment) && $this->Comment_UpdateComment($oComment)) {
                $this->Message_AddNoticeSingle(
                    $this->Lang_Get('comment_favourite_del_ok'), $this->Lang_Get('attention')
                );
                $this->Viewer_AssignAjax('bState', false);
                $this->Viewer_AssignAjax('iCount', $oComment->getCountFavourite());
            } else {
                $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                return;
            }
        }
    }

    /**
     * Обработка избранного - личное сообщение
     *
     */
    protected function EventFavouriteTalk() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }
        // * Можно только добавить или удалить из избранного
        $iType = getRequestStr('type', null, 'post');
        if (!in_array($iType, array('1', '0'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
        // *    Сообщение существует?
        if (!($oTalk = $this->Talk_GetTalkById(getRequestStr('idTalk', null, 'post')))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
        // * Сообщение уже в избранном?
        $oFavouriteTalk = $this->Talk_GetFavouriteTalk($oTalk->getId(), $this->oUserCurrent->getId());
        if (!$oFavouriteTalk && $iType) {
            $oFavouriteTalkNew = Engine::GetEntity(
                'Favourite',
                array(
                     'target_id'      => $oTalk->getId(),
                     'target_type'    => 'talk',
                     'user_id'        => $this->oUserCurrent->getId(),
                     'target_publish' => '1'
                )
            );
            if ($this->Talk_AddFavouriteTalk($oFavouriteTalkNew)) {
                $this->Message_AddNoticeSingle($this->Lang_Get('talk_favourite_add_ok'), $this->Lang_Get('attention'));
                $this->Viewer_AssignAjax('bState', true);
            } else {
                $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                return;
            }
        }
        if (!$oFavouriteTalk && !$iType) {
            $this->Message_AddErrorSingle($this->Lang_Get('talk_favourite_add_no'), $this->Lang_Get('error'));
            return;
        }
        if ($oFavouriteTalk && $iType) {
            $this->Message_AddErrorSingle($this->Lang_Get('talk_favourite_add_already'), $this->Lang_Get('error'));
            return;
        }
        if ($oFavouriteTalk && !$iType) {
            if ($this->Talk_DeleteFavouriteTalk($oFavouriteTalk)) {
                $this->Message_AddNoticeSingle($this->Lang_Get('talk_favourite_del_ok'), $this->Lang_Get('attention'));
                $this->Viewer_AssignAjax('bState', false);
            } else {
                $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                return;
            }
        }

    }

    /**
     * Обработка получения последних комментов
     * Используется в блоке "Прямой эфир"
     *
     */
    protected function EventStreamComment() {

        if ($aComments = $this->Comment_GetCommentsOnline('topic', Config::Get('block.stream.row'))) {
            $oViewer = $this->Viewer_GetLocalViewer();
            $oViewer->Assign('aComments', $aComments);
            $sTextResult = $oViewer->FetchWidget('stream_comment.tpl');
            $this->Viewer_AssignAjax('sText', $sTextResult);
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('block_stream_comments_no'), $this->Lang_Get('attention'));
            return;
        }
    }

    /**
     * Обработка получения последних топиков
     * Используется в блоке "Прямой эфир"
     *
     */
    protected function EventStreamTopic() {

        if ($oTopics = $this->Topic_GetTopicsLast(Config::Get('block.stream.row'))) {
            $oViewer = $this->Viewer_GetLocalViewer();
            $oViewer->Assign('oTopics', $oTopics);
            $sTextResult = $oViewer->FetchWidget('stream_topic.tpl');
            $this->Viewer_AssignAjax('sText', $sTextResult);
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('block_stream_topics_no'), $this->Lang_Get('attention'));
            return;
        }
    }

    /**
     * Обработка получения TOP блогов
     * Используется в блоке "TOP блогов"
     *
     */
    protected function EventBlogsTop() {

        // * Получаем список блогов и формируем ответ
        if ($aResult = $this->Blog_GetBlogsRating(1, Config::Get('block.blogs.row'))) {
            $aBlogs = $aResult['collection'];
            $oViewer = $this->Viewer_GetLocalViewer();
            $oViewer->Assign('aBlogs', $aBlogs);

            // Рендерим шаблон виджета
            $sTextResult = $oViewer->FetchWidget('blogs_top.tpl');
            $this->Viewer_AssignAjax('sText', $sTextResult);
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
    }

    /**
     * Обработка получения своих блогов
     * Используется в блоке "TOP блогов"
     *
     */
    protected function EventBlogsSelf() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }
        // * Получаем список блогов и формируем ответ
        if ($aBlogs = $this->Blog_GetBlogsRatingSelf($this->oUserCurrent->getId(), Config::Get('block.blogs.row'))) {
            $oViewer = $this->Viewer_GetLocalViewer();
            $oViewer->Assign('aBlogs', $aBlogs);
            $sTextResult = $oViewer->FetchWidget('blogs_top.tpl');
            $this->Viewer_AssignAjax('sText', $sTextResult);
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('block_blogs_self_error'), $this->Lang_Get('attention'));
            return;
        }
    }

    /**
     * Обработка получения подключенных блогов
     * Используется в блоке "TOP блогов"
     *
     */
    protected function EventBlogsJoin() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }
        // * Получаем список блогов и формируем ответ
        if ($aBlogs = $this->Blog_GetBlogsRatingJoin($this->oUserCurrent->getId(), Config::Get('block.blogs.row'))) {
            $oViewer = $this->Viewer_GetLocalViewer();
            $oViewer->Assign('aBlogs', $aBlogs);

            // Рендерим шаблон виджета
            $sTextResult = $oViewer->FetchWidget('blogs_top.tpl');
            $this->Viewer_AssignAjax('sText', $sTextResult);
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('block_blogs_join_error'), $this->Lang_Get('attention'));
            return;
        }
    }

    /**
     * Предпросмотр топика
     *
     */
    protected function EventPreviewTopic() {
        /**
         * Т.к. используется обработка отправки формы, то устанавливаем тип ответа 'jsonIframe' (тот же JSON только обернутый в textarea)
         * Это позволяет избежать ошибок в некоторых браузерах, например, Opera
         */
        $this->Viewer_SetResponseAjax('jsonIframe', false);

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }
        // * Допустимый тип топика?
        if (!$this->Topic_IsAllowTopicType($sType = getRequestStr('topic_type'))) {
            $this->Message_AddErrorSingle($this->Lang_Get('topic_create_type_error'), $this->Lang_Get('error'));
            return;
        }

        // * Создаем объект топика для валидации данных
        $oTopic = Engine::GetEntity('ModuleTopic_EntityTopic');
        $oTopic->_setValidateScenario($sType); // зависит от типа топика

        $oTopic->setTitle(strip_tags(getRequestStr('topic_title')));
        $oTopic->setTextSource(getRequestStr('topic_text'));
        $oTopic->setTags(getRequestStr('topic_tags'));
        $oTopic->setDateAdd(F::Now());
        $oTopic->setUserId($this->oUserCurrent->getId());
        $oTopic->setType($sType);

        // * Валидируем необходимые поля топика
        $oTopic->_Validate(array('topic_title', 'topic_text', 'topic_tags', 'topic_type'), false);
        if ($oTopic->_hasValidateErrors()) {
            $this->Message_AddErrorSingle($oTopic->_getValidateError());
            return false;
        }

        // * Формируем текст топика
        list($sTextShort, $sTextNew, $sTextCut) = $this->Text_Cut($oTopic->getTextSource());
        $oTopic->setCutText($sTextCut);
        $oTopic->setText($this->Text_Parser($sTextNew));
        $oTopic->setTextShort($this->Text_Parser($sTextShort));

        // * Готовим дополнительные поля, кроме файлов
        if ($oType = $oTopic->getContentType()) {
            //получаем поля для данного типа
            if ($aFields = $oType->getFields()) {
                $aValues = array();

                // вставляем поля, если они прописаны для топика
                foreach ($aFields as $oField) {
                    if (isset($_REQUEST['fields'][$oField->getFieldId()])) {

                        //текстовые поля
                        if (in_array($oField->getFieldType(), array('input', 'textarea', 'select'))) {
                            $sText = $this->Text_Parser($_REQUEST['fields'][$oField->getFieldId()]);
                        }
                        //поле ссылки
                        if ($oField->getFieldType() == 'link') {
                            $sText = $_REQUEST['fields'][$oField->getFieldId()];
                        }

                        //поле даты
                        if ($oField->getFieldType() == 'date') {
                            if (isset($_REQUEST['fields'][$oField->getFieldId()])) {

                                if (F::CheckVal($_REQUEST['fields'][$oField->getFieldId()], 'text', 6, 10)
                                    && substr_count($_REQUEST['fields'][$oField->getFieldId()], '.') == 2
                                ) {
                                    list($d, $m, $y) = explode('.', $_REQUEST['fields'][$oField->getFieldId()]);
                                    if (@checkdate($m, $d, $y)) {
                                        $sText = $_REQUEST['fields'][$oField->getFieldId()];
                                    }
                                }

                            }

                        }

                        if ($sText) {

                            $oValue = Engine::GetEntity('Topic_ContentValues');
                            $oValue->setFieldId($oField->getFieldId());
                            $oValue->setFieldType($oField->getFieldType());
                            $oValue->setValue($sText);
                            $oValue->setValueSource($_REQUEST['fields'][$oField->getFieldId()]);

                            $aValues[$oField->getFieldId()] = $oValue;

                        }
                    }
                }
                $oTopic->setTopicValues($aValues);
            }
        }

        // * Рендерим шаблон для предпросмотра топика
        $oViewer = $this->Viewer_GetLocalViewer();
        $oViewer->Assign('oTopic', $oTopic);
        $sTemplate = "topic_preview_{$oTopic->getType()}.tpl";
        if (!$this->Viewer_TemplateExists($sTemplate)) {
            $sTemplate = 'topic_preview_topic.tpl';
        }
        $sTextResult = $oViewer->Fetch($sTemplate);

        // * Передаем результат в ajax ответ
        $this->Viewer_AssignAjax('sText', $sTextResult);
        return true;
    }

    /**
     * Предпросмотр текста
     *
     */
    protected function EventPreviewText() {

        $sText = getRequestStr('text', null, 'post');
        $bSave = getRequest('save', null, 'post');

        // * Экранировать или нет HTML теги
        if ($bSave) {
            $sTextResult = htmlspecialchars($sText);
        } else {
            $sTextResult = $this->Text_Parser($sText);
        }
        // * Передаем результат в ajax ответ
        $this->Viewer_AssignAjax('sText', $sTextResult);
    }

    /**
     * Загрузка изображения
     *
     */
    protected function EventUploadImage() {
        /**
         * Т.к. используется обработка отправки формы, то устанавливаем тип ответа 'jsonIframe' (тот же JSON только обернутый в textarea)
         * Это позволяет избежать ошибок в некоторых браузерах, например, Opera
         */
        $this->Viewer_SetResponseAjax('jsonIframe', false);

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }
        $sFile = null;
        // * Был выбран файл с компьютера и он успешно загрузился?
        if ($aUploadedFile = $this->GetUploadedFile('img_file')) {
            $sFile = $this->Topic_UploadTopicImageFile($aUploadedFile, $this->oUserCurrent);
            if (!$sFile) {
                $sMessage = $this->Lang_Get('uploadimg_file_error');
                if ($this->Upload_GetError()) {
                    $sMessage .= ' (' . $this->Upload_GetErrorMsg() . ')';
                }
                $this->Message_AddErrorSingle($sMessage, $this->Lang_Get('error'));
                return;
            }
        } elseif (($sUrl = $this->GetPost('img_url')) && ($sUrl != 'http://')) {
            // * Загрузка файла по URL
            $sFile = $this->Topic_UploadTopicImageUrl($sUrl, $this->oUserCurrent);
        }
        // * Если файл успешно загружен, формируем HTML вставки и возвращаем в ajax ответе
        if ($sFile) {
            $oFile = Engine::GetEntity('Mresource');
            $oFile->SetTargetType('topic');
            $oFile->SetLink(0);
            $oFile->setType(ModuleMresource::TYPE_IMAGE);
            $oFile->setUrl($sFile);
            $oFile->setTopicId(0);
            $oFile->setUserId($this->oUserCurrent->getId());
            $this->Mresource_Add($oFile);

            $sText = $this->Img_BuildHTML($oFile->GetPathUrl(), $_REQUEST);
            $this->Viewer_AssignAjax('sText', $sText);
        } else {
            $this->Message_AddErrorSingle($this->Upload_GetErrorMsg(), $this->Lang_Get('error'));
        }
    }

    /**
     * Автоподставновка тегов
     *
     */
    protected function EventAutocompleterTag() {

        // * Первые буквы тега переданы?
        if (!($sValue = getRequest('value', null, 'post')) || !is_string($sValue)) {
            return;
        }
        $aItems = array();

        // * Формируем список тегов
        $aTags = $this->Topic_GetTopicTagsByLike($sValue, 10);
        foreach ($aTags as $oTag) {
            $aItems[] = $oTag->getText();
        }
        // * Передаем результат в ajax ответ
        $this->Viewer_AssignAjax('aItems', $aItems);
    }

    /**
     * Автоподставновка пользователей
     *
     */
    protected function EventAutocompleterUser() {

        // * Первые буквы логина переданы?
        if (!($sValue = getRequest('value', null, 'post')) || !is_string($sValue)) {
            return;
        }
        $aItems = array();

        // * Формируем список пользователей
        $aUsers = $this->User_GetUsersByLoginLike($sValue, 10);
        foreach ($aUsers as $oUser) {
            $aItems[] = $oUser->getLogin();
        }
        // * Передаем результат в ajax ответ
        $this->Viewer_AssignAjax('aItems', $aItems);
    }

    /**
     * Удаление/восстановление комментария
     *
     */
    protected function EventCommentDelete() {

        // * Есть права на удаление комментария?
        if (!$this->ACL_CanDeleteComment($this->oUserCurrent)) {
            $this->Message_AddErrorSingle($this->Lang_Get('not_access'), $this->Lang_Get('error'));
            return;
        }
        // * Комментарий существует?
        $idComment = getRequestStr('idComment', null, 'post');
        if (!($oComment = $this->Comment_GetCommentById($idComment))) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
        // * Устанавливаем пометку о том, что комментарий удален
        $oComment->setDelete(($oComment->getDelete() + 1) % 2);
        $this->Hook_Run('comment_delete_before', array('oComment' => $oComment));
        if (!$this->Comment_UpdateCommentStatus($oComment)) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }
        $this->Hook_Run('comment_delete_after', array('oComment' => $oComment));

        // * Формируем текст ответа
        if ($bState = (bool)$oComment->getDelete()) {
            $sMsg = $this->Lang_Get('comment_delete_ok');
            $sTextToggle = $this->Lang_Get('comment_repair');
        } else {
            $sMsg = $this->Lang_Get('comment_repair_ok');
            $sTextToggle = $this->Lang_Get('comment_delete');
        }
        // * Обновление события в ленте активности
        $this->Stream_write($oComment->getUserId(), 'add_comment', $oComment->getId(), !$oComment->getDelete());

        // * Показываем сообщение и передаем переменные в ajax ответ
        $this->Message_AddNoticeSingle($sMsg, $this->Lang_Get('attention'));
        $this->Viewer_AssignAjax('bState', $bState);
        $this->Viewer_AssignAjax('sTextToggle', $sTextToggle);
    }

    /**
     *
     */
    protected function EventFetch() {

        $sHtml = '';
        $bState = false;
        if ($sTpl = $this->GetParam(0)) {
            $sTpl = 'ajax.' . $sTpl . '.tpl';
            if ($this->Viewer_TemplateExists($sTpl)) {
                $sHtml = $this->Viewer_Fetch($sTpl);
                $bState = true;
            }
        }
        $this->Viewer_AssignAjax('sHtml', $sHtml);
        $this->Viewer_AssignAjax('bState', $bState);
    }

}

// EOF
