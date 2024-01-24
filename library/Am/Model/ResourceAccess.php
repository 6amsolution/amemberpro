<?php
/**
 * Class represents records from table resource_access
 * {autogenerated}
 * @property int $resource_access_id
 * @property int $resource_id
 * @property string $resource_type
 * @property string $fn enum('product_id','product_category_id','free')
 * @property int $id
 * @property int $start_days
 * @property int $start_payments
 * @property int $stop_days
 * @see Am_Table
 */
class ResourceAccess extends Am_Record
{
    const FN_PRODUCT = 'product_id';
    const FN_CATEGORY = 'product_category_id';
    const FN_FREE = 'free';
    const FN_FREE_WITHOUT_LOGIN = 'free_without_login';
    const FN_USER_GROUP = 'user_group_id';
    const FN_SPECIAL = 'special';

    const FN_SPECIAL_GUEST = 1;
    const FN_SPECIAL_AFF = 2;
    const FN_SPECIAL_RSS = 3;

    // resource_id types
    const FOLDER = 'folder';
    const PAGE = 'page';
    const LINK = 'link';
    const FILE = 'file';
    const VIDEO = 'video';
    const AUDIO = 'audio';
    const INTEGRATION = 'integration'; // plugin name must be appened
    const EMAILTEMPLATE = 'emailtemplate';

    /**
     * By default includes: FOLDER, PAGE, LINK, FILE, VIDEO
     * Can be modified by @see Am_Event::GET_RESOURCE_TYPES
     */
    const USER_VISIBLE_TYPES = 'user-visible-types';
    /**
     * By default includes: FOLDER, PAGE, LINK
     * Can be modified by @see Am_Event::GET_RESOURCE_TYPES
     */
    const USER_VISIBLE_PAGES = 'user-visible-pages';
    const ANY_PRODUCT = -1; // special category constant

    public function getId()
    {
        return $this->id;
    }

    public function getClass()
    {
        return $this->fn;
    }

    public function getClassTitle()
    {
        switch ($this->fn)
        {
            case self::FN_FREE : return ___('Free');
            case self::FN_FREE_WITHOUT_LOGIN : return ___('Free');
            case self::FN_CATEGORY : return ___('Category');
            case self::FN_PRODUCT : return ___('Product');
            case self::FN_USER_GROUP : return ___('User Group');
            case self::FN_SPECIAL: return ___('Special Conditions');
            default: return $this->fn;
        };
    }

    public function getTitle()
    {
        if ($this->fn == self::FN_FREE)
            return ___('Free Access');
        if ($this->fn == self::FN_FREE_WITHOUT_LOGIN)
            return ___('Free Access without log-in');
        $pr = null;
        if ($this->id)
        {
            if ($this->fn == self::FN_PRODUCT)
                $pr = $this->getDi()->productTable->load($this->id, false);
            elseif ($this->fn == self::FN_CATEGORY)
            {
                if ($this->id == self::ANY_PRODUCT)
                    return ___('Any product');
                else
                    $pr = $this->getDi()->productCategoryTable->load($this->id, false);
            }
            elseif ($this->fn == self::FN_USER_GROUP)
            {
                $pr = $this->getDi()->userGroupTable->load($this->id, false);
            }
            elseif ($this->fn == self::FN_SPECIAL)
            {
                $opt = $this->getDi()->resourceAccessTable->getFnSpecialOptions();
                return !empty($opt[$this->id]) ? $opt[$this->id] : "";
            }
        }
        if (!$pr) return sprintf('(%s #%d)', $this->getClass(), $this->getId());
        return sprintf('(%d) %s', $pr->pk(), $pr->title);
    }

    public function getStart()
    {
        if ($this->date_based) return "a";
        if ($this->start_payments) return "{$this->start_payments}p";
        return strlen($this->start_days) ? "{$this->start_days}d" : null;
    }

    public function getStop($parse_forever = true)
    {
        if ($this->stop_days == -1 && $parse_forever) return "forever";
        return strlen($this->stop_days) ? "{$this->stop_days}d" : null;
    }

    public function hasCustomStartStop()
    {
        return strlen($this->stop_day) || strlen($this->start_day);
    }

    public function isAnyProducts()
    {
        return empty($this->product_id) && @$this->product_category_id <= 0;
    }

    public function isFree()
    {
        return ($this->fn == self::FN_FREE) || ($this->fn == self::FN_FREE_WITHOUT_LOGIN);
    }

    public function render()
    {
        $out = "";
        $out .= "<b>" . $this->getTitle() . "</b> ";
        if ($this->date_based)
            $out .= "[access by publish date]";
        else {
            if ($this->start_days || $this->start_payments)
                $out .= "from <i>" . $this->getStart() . "</i> ";
            if ($this->stop_days)
                $out .= "to <i>" . $this->getStop() . "</i>";
        }
        return $out;
    }
}

class ResourceAccessTable extends Am_Table
{
    protected $_key = 'resource_access_id';
    protected $_table = '?_resource_access';
    protected $_recordClass = 'ResourceAccess';
    protected $_types = [];

    /**
     * @return Am_Query
     */
    protected function _getBaseQuery($joinConditions = "")
    {
        if ($joinConditions)
            $joinConditions = "(" . $joinConditions . ") AND ";
        $q = new Am_Query($this, 'r');
        $q->clearFields();
        $q->addField('DISTINCT r.resource_id', 'resource_id');
        $q->addField('resource_type', 'resource_type');
        $q->addField("fn", 'fn');
        $q->addField("id", 'fn_id');
        $q->addField("c.begin_date");
        $q->addField("c.expire_date");
        $q->leftJoin("?_access_cache", "c",
                    "
                    $joinConditions
                    (((c.fn = r.fn) AND (c.id = r.id)) OR (c.fn = 'product_id' AND r.fn='product_category_id' AND r.id=-1))
                    AND (
                            (c.status='active' AND IFNULL(c.days,0) BETWEEN IFNULL(r.start_days,0) AND IFNULL(r.stop_days, 90000) AND c.payments_count >= IFNULL(r.start_payments,0))
                            OR
                            (c.days >= IFNULL(r.start_days,0) AND r.stop_days = -1 AND c.payments_count >= IFNULL(r.start_payments,0))
                       )");
        // is available if free, or if user has equal subscription record in access_cache
        $q->addWhere("(r.fn IN ('free', 'free_without_login') OR c.user_id IS NOT NULL)");
        $q->addOrderRaw("(SELECT ras.sort_order
                      FROM ?_resource_access_sort ras
                      WHERE ras.resource_id=r.resource_id AND ras.resource_type=r.resource_type
                      LIMIT 1), r.resource_id, r.resource_type");
        return $q;
    }

    /**
     * Return resources currently allowed for user
     * @param User $user
     * @param array|single type constant from ResourceAccess $types
     * @return array of records (as array)
     */
    function selectAllowedResources(User $user, $types = null)
    {
        // select product_id/product_category_id, type, number of days
        $q = $this->_getBaseQuery("c.user_id=".intval($user->pk()));
        if ($types !== null)
            $q->addWhere("resource_type IN (?a)", is_array($types) ? $types : [$types]);
        return $this->_db->fetchRows($q->query());
    }

    /**
     * Return allowed product emails as objects
     * @return array of ResourceAbstract
     * @see self::selectAllowedResources
     */
    function getProductWelcomeEmails($product_ids)
    {
        $ret = [];
        $groups = $this->getDi()->db->selectCol("SELECT product_category_id from ?_product_product_category where product_id IN (?a)",$product_ids);
        $groups[]= -1;
        $q = new Am_Query($this, 'r');
        $q->clearFields();
        $q->addField('DISTINCT r.resource_id', 'resource_id');
        $q->leftJoin('?_email_template', 'et', '(r.resource_id = et.email_template_id)');
        $q->addWhere("resource_type = ?", ResourceAccess::EMAILTEMPLATE);
        $q->addWhere("(r.fn = 'product_id' AND r.id IN (?a) ) OR (r.fn = 'product_category_id' AND r.id IN (?a) )",$product_ids,$groups);
        $q->addWhere('et.name=?',EmailTemplate::PRODUCTWELCOME);
        $q->groupBy('resource_id');
        $res = $this->_db->fetchRows($q->query());
        $ret = [];
        foreach ($res as $r)
            $ret[] = $this->getDi()->emailTemplateTable->load($r['resource_id']);
        return $ret;
    }

    /**
     * Return allowed product emails as objects
     * @return array of ResourceAbstract
     * @see self::selectAllowedResources
     */
    function getPaymentEmails($product_ids)
    {
        $ret = [];
        $groups = $this->getDi()->db->selectCol("SELECT product_category_id from ?_product_product_category where product_id IN (?a)",$product_ids);
        $groups[]= -1;
        $q = new Am_Query($this, 'r');
        $q->clearFields();
        $q->addField('DISTINCT r.resource_id', 'resource_id');
        $q->leftJoin('?_email_template', 'et', '(r.resource_id = et.email_template_id)');
        $q->addWhere("resource_type = ?", ResourceAccess::EMAILTEMPLATE);
        $q->addWhere("(r.fn = 'product_id' AND r.id IN (?a) ) OR (r.fn = 'product_category_id' AND r.id IN (?a) )",$product_ids,$groups);
        $q->addWhere('et.name=?',EmailTemplate::PAYMENT);
        $q->groupBy('resource_id');
        $res = $this->_db->fetchRows($q->query());
        $ret = [];
        foreach ($res as $r)
            $ret[] = $this->getDi()->emailTemplateTable->load($r['resource_id']);
        return $ret;
    }

    /**
     * Return allowed resources as objects
     * @return array of ResourceAbstract
     * @see self::selectAllowedResources
     */
    function getAllowedResources(User $user, $types = null, $groupByType = true)
    {
        $ret = [];
        $res = $this->selectAllowedResources($user, $types = $this->getResourceTypes($types));
        $ids = [];
        $dates = [];
        $order = [];
        $i = 0;
        foreach ($res as $k => $r)
        {
            $ids[$r['resource_type']][$r['resource_id']] = ['fn' => $r['fn'], 'id' => $r['fn_id']];
            $dates[$r['resource_type']][$r['resource_id']] = ['expire_date' => $r['expire_date'], 'begin_date' => $r['begin_date']];
            $order[$r['resource_type'].'_'.$r['resource_id']] = $i++;
        }
        $ret = [];
        foreach ($ids as $resource_type => & $container)
        {
            $table = $this->getDi()->getService(lcfirst(toCamelCase($resource_type)) . 'Table');
            /* @var $table Am_Table */
            foreach ($table->loadIds(array_keys($container)) as $rec)
            {
                $id = $rec->pk();
                if (isset($container[$id]))
                {
                    $k = $order["{$resource_type}_{$id}"]; // get position in result
                    $rec->fn_id = $container[$id]; // assign product_title to email template
                    $rec->expire_date = $dates[$resource_type][$id]['expire_date'];
                    $rec->begin_date = $dates[$resource_type][$id]['begin_date'];
                    $ret[$k] = $rec;
                } else {
                    throw new Am_Exception_InternalError("->loadIds returned id[$id] not specified in request " . implode(",", array_keys($container)));
                }
            }
        }
        $ret = $this->getDi()->hook->filter($ret, Am_Event::GET_ALLOWED_RESOURCES, [
            'user' => $user,
            'types' => $types
        ]);
        ksort($ret);
        return $ret;
    }

    /**
     * Expand types constant to list of resource types
     *
     * @param string|array|null|enum(ResourceAccess::USER_VISIBLE_TYPES, ResourceAccess::USER_VISIBLE_PAGES) $types
     * @return array|null
     * @see Am_Event::GET_RESOURCE_TYPES
     */
    protected function getResourceTypes($types)
    {
        if (is_null($types) ||
            is_array($types) ||
            !in_array($types, [
                ResourceAccess::USER_VISIBLE_TYPES,
                ResourceAccess::USER_VISIBLE_PAGES
            ])) return $types;

        if (isset($this->_types[$types])) return $this->_types[$types];

        $res = [];

        if ($types === ResourceAccess::USER_VISIBLE_TYPES) {
            $res = [
                ResourceAccess::FOLDER,
                ResourceAccess::FILE,
                ResourceAccess::PAGE,
                ResourceAccess::LINK,
                ResourceAccess::VIDEO,
            ];
        } elseif ($types === ResourceAccess::USER_VISIBLE_PAGES) {
            $res = [
                ResourceAccess::FOLDER,
                ResourceAccess::PAGE,
                ResourceAccess::LINK,
            ];
        }

        $res = $this->getDi()->hook->filter($res, Am_Event::GET_RESOURCE_TYPES, [
            'type' => $types
        ]);

        $this->_types[$types] = $res;

        return $res;
    }

    function userHasAccess(User $user, $id, $type)
    {
        $q = $this->_getBaseQuery("c.user_id=".intval($user->pk()));
        $q->addWhere("resource_type=?", $type);
        $q->addWhere("resource_id=?", $id);
        $ret = (bool)$q->selectPageRecords(0, 1);

        return $this->getDi()->hook->filter($ret, Am_Event::USER_HAS_ACCESS, [
            'resource_id' => $id,
            'resource_type' => $type,
            'user' => $user
        ]);
    }

    /**
     * Return true if not logged-in visitor has access
     * to the resource
     */
    function guestHasAccess($id, $type)
    {
        $ret = (bool)$this->_db->selectCell("SELECT resource_access_id
            FROM {$this->_table}
            WHERE resource_type=? AND resource_id=? AND fn=?",
                $type, floatval($id), ResourceAccess::FN_FREE_WITHOUT_LOGIN);

        return $this->getDi()->hook->filter($ret, Am_Event::GUEST_HAS_ACCESS, [
            'resource_id' => $id,
            'resource_type' => $type
        ]);
    }

    function updateCache($userId = null)
    {
        if ($userId === null)
        {
            $this->_db->query("ALTER TABLE ?_access_cache DISABLE KEYS");
            $this->_db->query("TRUNCATE TABLE ?_access_cache");
            $this->_db->query("INSERT INTO ?_access_cache SET "
                . "user_id=0, fn=?, id=?, status='active'",
                ResourceAccess::FN_SPECIAL, ResourceAccess::FN_SPECIAL_GUEST
            );
        } else
            $this->_db->query("DELETE FROM ?_access_cache {WHERE user_id=?d}", $userId ? $userId : DBSIMPLE_SKIP);

        $productCatsCache = $this->getDi()->productCategoryTable->getCategoryProducts();
        $dat = $this->getDi()->sqlDate;
        $today = $this->getDi()->sqlDate;

        $q = $this->_db->queryResultOnly("
            SELECT
            user_id,
            product_id,
            UNIX_TIMESTAMP(begin_date) AS begin_date,
            UNIX_TIMESTAMP(LEAST(?, expire_date)) AS expire_date
            ,begin_date AS sql_begin_date
            ,expire_date AS sql_expire_date
            FROM ?_access
            WHERE {user_id = ?d AND } begin_date <= ?
            ORDER BY user_id
        ",
            $this->getDi()->sqlDate,
            $userId ? $userId : DBSIMPLE_SKIP,
            $this->getDi()->sqlDate);
        $rows = [];
        $insert = [];
        $lastUserId = null;
        while ($r = $this->_db->fetchRow($q))
        {
            if (($r['user_id'] != $lastUserId) && $rows)
            {
                $insert = array_merge($insert, $this->_updateCacheUser($rows, $productCatsCache, $dat, $today));
                if (count($insert) > 100)
                {
                    $this->_insertCache($insert);
                    $insert = [];
                }
                $rows = [];
            }
            $rows[] = $r;
            $lastUserId = $r['user_id'];
        }
        $this->_db->freeResult($q);
        if ($rows)
            $insert = array_merge($insert, $this->_updateCacheUser($rows, $productCatsCache, $dat, $today));
        if ($insert)
            $this->_insertCache($insert);

        $this->_db->query("INSERT IGNORE INTO ?_access_cache (user_id, fn, id, days, begin_date, expire_date, payments_count, status)
            SELECT user_id, 'user_group_id', user_group_id, NULL, NULL, NULL, 0, 'active' FROM ?_user_user_group
            WHERE 1 {AND user_id=?}", $userId === null ? DBSIMPLE_SKIP : $userId);

        // insert affiliate status
        $this->_db->query("INSERT IGNORE INTO ?_access_cache (user_id, fn, id, days, begin_date, expire_date, payments_count, status)
            SELECT user_id, 'special', ?d, NULL, NULL, NULL, NULL, 'active' FROM ?_user u
            WHERE u.is_affiliate>0 {AND u.user_id=?}",
            ResourceAccess::FN_SPECIAL_AFF,
            $userId === null ? DBSIMPLE_SKIP : $userId);

        if ($userId === null)
        {
            $this->_db->query("ALTER TABLE ?_access_cache ENABLE KEYS");
        }
        $this->_db->query("UPDATE ?_access_cache ac
            SET payments_count = (SELECT COUNT(invoice_payment_id)
                FROM ?_invoice_payment ip
                INNER JOIN ?_invoice_item ii USING (invoice_id)
                WHERE ip.amount - IFNULL(ip.refund_amount, 0) > 0
                    AND ip.user_id = ac.user_id
                    AND ii.item_id=ac.id)
            WHERE {ac.user_id=?d AND } ac.fn = 'product_id'
        ", $userId === null ? DBSIMPLE_SKIP : $userId);
    }

    function _insertCache(array $insert)
    {
        // todo - direct query : disable _expandPlaceholdersCallback
        $prefix = $this->_db->getPrefix();
        $this->_db->queryQuick("INSERT IGNORE INTO {$prefix}access_cache
            (user_id, fn, id, days, begin_date, expire_date, status)
            VALUES\n" .
            implode(",", $insert));
    }

    function _updateCacheUser(array $rows, array $productCatsCache, $dat, $today)
    {
        $active = $insert = [];
        $min = $max = [];
        foreach ($rows as $r)
        {
            $pid = $r['product_id'];
            $dates[$pid][] = [$r['begin_date']+43200, 0];
            $dates[$pid][] = [$r['expire_date']+43200, 1];
            if (empty($min[$pid]) || ($min[$pid] > $r['sql_begin_date'])) $min[$pid] = $r['sql_begin_date'];
            if (empty($max[$pid]) || ($max[$pid] < $r['sql_expire_date'])) $max[$pid] = $r['sql_expire_date'];
            if (($r['sql_begin_date'] <= $today) && ($today <= $r['sql_expire_date']))
            {
                $active[$r['product_id']] = "active";
            } elseif (empty($active[$r['product_id']])) {
                $active[$r['product_id']] = "expired";
            }
        }

        $len = [];
        foreach ($dates as $pid => $d)
        {
            sort($dates[$pid]);
            $len[$pid] = $this->_calcKleeLen($dates[$pid]);
            $insert[] = sprintf("(%d,'product_id',%d,%d,'%s','%s','%s')",
                $r['user_id'], $pid,
                $len[$pid], $min[$pid], $max[$pid], $active[$pid]
            );
        }
        foreach ($productCatsCache as $pc => $pids)
        {
            $catDates = [];
            $catActive = 'expired';
            $catMax = $catMin = [];
            foreach ($pids as $pid)
            {
                if (!empty($dates[$pid]))
                {
                    $catDates = array_merge($catDates, $dates[$pid]);
                    if ($active[$pid] == 'active')
                        $catActive = 'active';
                    $catMin[$pid] = $min[$pid];
                    $catMax[$pid] = $max[$pid];
                }
            }
            if (!$catDates) continue;
            if (count($catMax) == 1) // if there's only one product found
            {
                $pid = key($catMax);
                $insert[] = sprintf("(%d,'product_category_id',%d,%d,'%s','%s','%s')",
                    $r['user_id'], $pc,
                    $len[$pid], $min[$pid], $max[$pid], $active[$pid]);
            } else {
                sort($catDates);
                $insert[] = sprintf("(%d,'product_category_id',%d,%d,'%s','%s','%s')",
                    $r['user_id'], $pc,
                    $this->_calcKleeLen($catDates), min($catMin), max($catMax), $catActive
                );
            }
        }
        return $insert;
    }

    function _calcKleeLen($datesArray)
    {
        $len = 0;
        $c = 0;
        foreach ($datesArray as $i => $v)
        {
            if ($c && $i) // count only inside an interval
            {
                $len += $v[0] - $datesArray[$i-1][0];
            }
            if ($v[1]) // if end
                --$c; // we have finished an interval
            else {
                if (!$c) // opens new interval
                    $len += 86400;
                ++$c; // we have started a new interval
            }
        }
        return round($len/86400);
    }

    /**
     * select resource accessible for customers using
     * records (user_id, resource_id, resource_type, login, email)
     * @return Am_Query
     */
    function getResourcesForMembers($types = null, $condition="1=1")
    {
        if ($types && !is_array($types))
            $types = (array)$types;

        $qfree = new Am_Query($this, 'rfree');
        $qfree->crossJoin('?_user', 'u')
            ->clearFields()
            ->addField('u.user_id')
            ->addField('rfree.resource_id')
            ->addField('rfree.resource_type')
            ->addField('u.login')
            ->addField('u.email')
            ->addField("rfree.fn", 'fn')
            ->addField("rfree.id", 'fn_id')
            ->groupBy('user_id, resource_id, resource_type', 'u')
            ->addWhere("rfree.fn IN ('free', 'free_without_login')")
            ->addWhere("(
                            (rfree.start_days IS NULL AND rfree.stop_days IS NULL)
                            OR
                            (CEIL((UNIX_TIMESTAMP() - UNIX_TIMESTAMP(u.added))/86400) BETWEEN IFNULL(rfree.start_days,0) AND IFNULL(rfree.stop_days, 90000))
                            OR
                            (CEIL((UNIX_TIMESTAMP() - UNIX_TIMESTAMP(u.added))/86400) >= IFNULL(rfree.start_days,0) AND rfree.stop_days = -1)
                       )");
        if ($types)
            $qfree->addWhere('rfree.resource_type IN (?a) AND ' . $condition, $types);

        $q = $this->_getBaseQuery();
        $q->clearFields();
        $q->clearOrder();
        $q->addField('DISTINCT c.user_id')
            ->addField('r.resource_id')
            ->addField('r.resource_type')
            ->addField('u.login')
            ->addField('u.email')
            ->addField("r.fn", 'fn')
            ->addField("r.id", 'fn_id')
            ->leftJoin('?_user', 'u', 'u.user_id=c.user_id')
            ->addOrder('user_id')
            // yes we need that subquery in subquery to mask field names
            // to get access of fields of main query (!)
            ->addOrderRaw("(SELECT _sort_order
                 FROM ( SELECT sort_order as _sort_order,
                        resource_type as _resource_type,
                        resource_id as _resource_id
                      FROM ?_resource_access_sort ras) AS _ras
                 WHERE _resource_id=resource_id AND _resource_type=resource_type LIMIT 1),
                 resource_id, resource_type")
            ->groupBy('user_id, resource_id, resource_type', 'c')
        // we will use separate query for free records
            ->addWhere("r.fn NOT IN ('free', 'free_without_login')")
            ->addUnion($qfree);

        if ($types)
            $q->addWhere('r.resource_type IN (?a) AND ' . $condition, $types);

        return $q;
    }

    function getFnValues()
    {
        return [
            ResourceAccess::FN_CATEGORY,
            ResourceAccess::FN_PRODUCT,
            ResourceAccess::FN_USER_GROUP,
            ResourceAccess::FN_FREE,
            ResourceAccess::FN_FREE_WITHOUT_LOGIN,
            ResourceAccess::FN_SPECIAL,
        ];
    }

    public function getFnSpecialOptions()
    {
        $ret = [
            ResourceAccess::FN_SPECIAL_GUEST => ___('Guests'),
        ];
        if ($this->getDi()->modules->isEnabled('aff'))
            $ret[ResourceAccess::FN_SPECIAL_AFF] = ___('Affiliates');
        if (defined('WP_ADMIN') && WP_ADMIN)
            $ret[ResourceAccess::FN_SPECIAL_RSS] = ___('RSS Feed');
        return $ret;
    }

    /**
     * Return available types of resources
     * @return array
     *    key: type
     *    value: ResourceAccessTable
     */
    function getAccessTables()
    {
        if (empty($this->_accessTables))
        {
            $di = $this->getDi();
            foreach([
                $di->folderTable, $di->fileTable,
                $di->pageTable, $di->integrationTable,
                $di->emailTemplateTable, $di->linkTable,
                $di->videoTable,
                    ] as $t)
                    $this->registerAccessTable($t);
            $di->hook->call(Am_Event::INIT_ACCESS_TABLES, ['registry' => $this]);
        }
        return $this->_accessTables;
    }

    function registerAccessTable(ResourceAbstractTable $t)
    {
        $this->_accessTables[$t->getAccessType()] = $t;
    }

    function syncSortOrder()
    {
        $db = $this->getDi()->db;
        //
        foreach ($this->getAccessTables() as $k => $t)
        {
            // delete records that are not found in master table
            $db->query("DELETE FROM ?_resource_access_sort
                WHERE resource_type=?
                AND NOT EXISTS (
                    SELECT * FROM ?# t
                    WHERE t.?#=resource_id
                    LIMIT 1
                    )
            ", $k, $t->getName(), $t->getKeyField());

            // add records that present in master table
            $x = (int)$db->selectCell("SELECT MAX(sort_order)
                FROM ?_resource_access_sort");
            if (!$x) $x = 3000;
            $key = $t->getKeyField();
            $db->query("INSERT IGNORE
                INTO ?_resource_access_sort
                (resource_access_sort_id, resource_id, resource_type, sort_order)
                SELECT
                    null,
                    $key as resource_id,
                    '$k' as resource_type,
                    $key + $x as sort_order
                    FROM ?#
                    {WHERE name IN (?a) }
                ", $t->getName(),
                 $t instanceof EmailTemplateTable ?
                    [EmailTemplate::AUTORESPONDER, EmailTemplate::EXPIRE] :
                    DBSIMPLE_SKIP);
        }
    }

    function clearAccess($id, $type)
    {
        return $this->getDi()->resourceAccessTable->deleteBy(
            [
                'resource_type' => $type,
                  'resource_id' => $id
            ]
        );
    }

    /**
     * Add a resource access record
     * @param int $recordId
     * @param enum $recordType
     * @param int $itemId product# or category# or -1
     * @param string $startString 1d or 3m or 0d - for zero autoresponder
     * @param string $stopString
     * @param bool $isProduct is a product or category
     * @return ResourceAccess
     */
    public function addAccessListItem($recordId, $recordType, $itemId, $startString, $stopString, $fn)
    {
        $fa = $this->getDi()->resourceAccessRecord;
        $fa->resource_type = $recordType;
        $fa->resource_id = $recordId;

        $fa->fn = $fn;
        $fa->id = $itemId;
        $fa->start_days = null;
        $fa->start_payments = 0;
        $fa->stop_days = null;
        $fa->date_based = 0;
        if (preg_match('/^(\d+)p$/', strtolower($startString), $regs))
        {
            $fa->start_payments = $regs[1];
        } elseif (preg_match('/^(-?\d+)(\w+)$/', strtolower($startString), $regs)) {
            $fa->start_days = $regs[1];
        } elseif ($startString == 'a') {
            $fa->date_based = 1;
        }
        if (preg_match('/^(-?\d+)(\w+)$/', strtolower($stopString), $regs))
        {
            $fa->stop_days = $regs[1];
        }
        $fa->insert();
        return $fa;
    }

    public function setAccess($recordId, $recordType, $access)
    {
        $this->clearAccess($recordId, $recordType);

        foreach($this->getFnValues() as $fn) {
            if(!empty($access[$fn])) {
                foreach ($access[$fn] as $id => $params) {
                    if (!is_array($params))
                        $params = json_decode($params, true);

                    $this->addAccessListItem($recordId, $recordType, $id, $params['start'], $params['stop'], $fn);
                }
            }
        }
    }

    public function getAccessList($recordId, $recordType)
    {
        return $this->findBy([
                    'resource_type' => $recordType,
                    'resource_id' => $recordId
        ]);
    }

    /**
     * Return unique access cache hash to detect if something changed in settings
     * @param type $recordId
     * @param type $recordType
     * @return string
     */
    public function getAccessListHash($recordId, $recordType)
    {
        $fields = 'fn,start_days,start_payments,stop_days,date_based';

        return $this->_db->selectCell("SELECT "
            . "SHA1(GROUP_CONCAT(CONCAT_WS('-',$fields) ORDER BY CONCAT_WS('-',$fields))) "
            . "FROM ?_resource_access "
            . "WHERE resource_id=?d AND resource_type=?",
            $recordId, $recordType
        );
    }

    /**
     * Check if access is allowed by provided conditions in PHP code instead of MySQL
     * against ?_access_cache table
     * @topo optimization? caching?
     * @todo special
     */
    function checkConditions($user_id, array $conditions)
    {
        $rows = $this->getDi()->db->select("SELECT *, CONCAT(fn,':',id) AS ARRAY_KEY FROM ?_access_cache"
            . " WHERE user_id=?d", $user_id);
        foreach ($conditions as $cond)
        {
            $check_rows = [];
            if ($cond['fn'] == 'product_category_id' && $cond['id'] == '-1')
            {
                // any category special code
                foreach ($rows as $r)
                    if ($r['fn'] == 'product_id')
                        $check_rows[] =  $r;
            } else {
                $key = $cond['fn'] . ':' . $cond['id'];
                if (empty($rows[$key])) continue;
                $check_rows = [ $rows[$key] ];
            }
            foreach ($check_rows as $row)
            {
                //parsed conditions vars
                $start_days = 0;
                $start_payments = 0;
                $stop_days = PHP_INT_MAX;
                // parse from array to vars
                if (!empty($cond['start']) && preg_match('/^(\d+)(d|p)$/', strtolower($cond['start']), $regs)) {
                    if ($regs[2] == 'd') $start_days = $regs[1];
                    if ($regs[2] == 'p') $start_payments = $regs[1];
                }
                if (!empty($cond['stop']) && preg_match('/^([-]?\d+)(d)$/', strtolower($cond['stop']), $regs))
                {
                    if ($regs[2] == 'd') $stop_days = $regs[1];
                }
                // check
                if (($stop_days != '-1') && ($row['status'] != 'active')) continue; // only allow expired status for 'forever'=-1d
                if ($start_days > $row['days']) continue;
                if (($stop_days >= 0) && ($stop_days < $row['days'])) continue;
                if ($start_payments > $row['payments_count']) continue;
                return true; // we got it if any condition matched
            }
        }
        return false; // no matches found
    }
}

