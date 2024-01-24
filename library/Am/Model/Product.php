<?php
/**
 * Class represents records from table products
 * {autogenerated}
 * @property int $product_id
 * @property string $title
 * @property string $description
 * @property string $trial_group
 * @property string $start_date
 * @property string $currency
 * @property int $tax_group
 * @property string $sort_order
 * @property string $renewal_group
 * @property date $start_date_fixed
 * @property string $require_other
 * @property string $prevent_if_other
 * @property string $require_other_group
 * @property string $prevent_if_other_group
 * @property string $paysys_id
 * @property string $comment
 * @property int $default_billing_plan_id
 * @property int $is_tangible
 * @property bool $is_disabled
 * @see Am_Table
 * @package Am_Invoice
 */

class Product extends Am_Record_WithData implements IProduct
{
    /** calculate start date by payment date */
    const SD_PAYMENT = 'payment';
    /** calculate start date by product payments */
    const SD_PRODUCT = 'product';
    /** calculate start date by renewal groups payments */
    const SD_GROUP = 'group';
    /** set fixed start date */
    const SD_FIXED = 'fixed';
    const SD_NEXT_DAY = 'next-day';
    const SD_WEEKDAY_MON = 'w1'; //Monday
    const SD_WEEKDAY_TUE = 'w2'; //Tuesday
    const SD_WEEKDAY_WED = 'w3'; //Wednesday
    const SD_WEEKDAY_THU = 'w4'; //Thursday
    const SD_WEEKDAY_FRI = 'w5'; //Friday
    const SD_WEEKDAY_SAT = 'w6'; //Saturday
    const SD_WEEKDAY_SUN = 'w0'; //Sunday
    const SD_MONTH_1 = 'm1'; //1st Day of Month
    const SD_MONTH_15 = 'm15'; //15th Day of Month
    const SD_THIS_MONTH_FIRST = 'm-first';
    const SD_THIS_MONTH_LAST = 'm-last';


    /** @var BillingPlan */
    protected $_plan;
    /** @var array */
    protected $_options = [];

    /**
     * @return bool
     */
    function isRecurring(){
        return (bool)$this->getBillingPlan()->rebill_times;
    }
    /**
     * Run htmlspecialchars(strip_tags()) for the string
     * It is useful for strings that may contain html entities but we would
     * not want to see it here, for example: product title or description
     * @param string string to escape
     * @return string escaped string
     */

    static function stripEscape($string){
        return htmlspecialchars(strip_tags($string), null, 'UTF-8', false);
    }

    /**
     * Return title of product
     */
    function getTitle($escaped=true){
        $title = ___($this->title);
        return $escaped ? $this->stripEscape($title) : str_ireplace('<script>', '', $title);
    }
    /**
     * Return description of product
     */
    function getDescription($escaped=true){
        $title = ___($this->description);
        return $escaped ? $this->stripEscape($title) : str_ireplace('<script>', '', $title);
    }

    /**
     * @return Am_Currency
     */
    function getCurrency($value = null)
    {
        $this->getBillingPlan()->getCurrency($value);
    }

    function getProductId() { return $this->product_id; }
    /**
     * Return short type of the item, ex. for Product returns "product"
     */
    function getType() { return 'product'; }
    //function getTitle()
    //function getDescription();
    function getFirstPrice() {
        return $this->getBillingPlan()->first_price;
    }
    function getFirstPeriod() {
        return $this->getBillingPlan()->first_period;
    }
    /**
     * Rebilling mode
     * @return int 0:"No Rebilling", 1:"Charge Second Price Once", a number:"Rebill x Times: ", 99999:"Unlimited Recurring Billing"
     */
    function getRebillTimes(){
        return $this->getBillingPlan()->rebill_times;
    }
    function getSecondPrice(){
        return $this->getBillingPlan()->second_price;
    }
    function getSecondPeriod(){
        return $this->getBillingPlan()->second_period;
    }
    function getCurrencyCode(){
        return $this->getBillingPlan()->currency;
    }
    function getTaxGroup()
    {
        return $this->tax_group;
    }
    /**
     * Can the item be shipped? Should we calculate shipping
     * charges for it?
     */
    function getIsTangible(){
        return $this->is_tangible;
    }
    /**
     * Can qty of the item in the Invoice be not equal to 1?
     * For subscriptions this must be almost always "false"
     * For deliverable goods like cups this must be "true"
     */
    function getIsCountable(){
        $bp = $this->getBillingPlan();
        return $bp->get('qty') != 1 || $bp->get('variable_qty');
    }

    public function getQty()
    {
        return $this->getBillingPlan()->get('qty', 1);
    }

    public function getIsVariableQty()
    {
        return $this->getBillingPlan()->get('variable_qty', 0);
    }

    /** param array $categories - array of id# */
    function setCategories(array $categories)
    {
        $this->getAdapter()->query("DELETE FROM ?_product_product_category WHERE product_id=?d {AND product_category_id NOT IN (?a)}",
                $this->product_id, $categories ? $categories : DBSIMPLE_SKIP);
        if (!$categories) return;
        $vals = [];
        foreach ($categories as $id)
            $vals[] = sprintf("(%d,%d)", $this->product_id, $id);
        $this->getAdapter()->query("INSERT IGNORE INTO ?_product_product_category
            (product_id, product_category_id)
            VALUES " . implode(", ", $vals));
    }
    /** @return array of id# */
    function getCategories()
    {
        if (empty($this->product_id)) return [];
        return $this->getAdapter()->selectCol("SELECT product_category_id FROM ?_product_product_category WHERE product_id=?d", $this->product_id);
    }

    function getCategoryTitles()
    {
        if (empty($this->product_id)) return [];
        return $this->getAdapter()->selectCol("SELECT pc.title FROM ?_product_category pc
            LEFT JOIN ?_product_product_category ppc USING (product_category_id) WHERE product_id=?d", $this->product_id);
    }
    /** @return array of BillingPlan */
    function getBillingPlans($onlyEnabled = false)
    {
        if (empty($this->product_id)) return [];
        $ret = $this->getDi()->billingPlanTable->getForProduct($this->product_id, null, $onlyEnabled);
        foreach ($ret as $r) $r->_setProduct($this);
        return $ret;
    }
    /** @return BillingPlan */
    function getBillingPlan($throwException = true)
    {
        if (!$this->_plan)
            if ($this->default_billing_plan_id)
                $this->_plan = $this->getDi()->billingPlanTable->load($this->default_billing_plan_id);
            else
                $this->_plan = current($this->getDi()->billingPlanTable->getForProduct($this->product_id, 1));
        if (!$this->_plan && $throwException)
            throw new Am_Exception_Configuration("No billing plan defined for product# " . $this->pk());
        return $this->_plan;
    }
    function getBillingOptions()
    {
        $ret = [];
        foreach ($this->getBillingPlans(true) as $plan)
            $ret[ $plan->plan_id ] = $plan->title . ' - ' . $plan->getTerms();
        return $ret;
    }
    /** @return BillingPlan and set it as "current" for this product object */
    function createBillingPlan()
    {
        if (!$this->pk())
            throw new Am_Exception_InternalError("Could not run [createBillingPlan] on not-saved product");
        $p = $this->getDi()->billingPlanRecord;
        $p->product_id = $this->pk();
        $this->setBillingPlan($p);
        return $p;
    }
    /**
     * @param BillingPlan|int $plan
     * @return Product provides fluent interface
     */
    function setBillingPlan($plan)
    {
        if ($plan instanceof BillingPlan)
            $this->_plan = $plan;
        else
        {
            $p = $this->getDi()->billingPlanTable->load(intval($plan), true);
            if ($p->product_id != $this->product_id)
                throw new Am_Exception_InternalError("Billing plan from another product cannot be used");
            $this->_plan = $p;
        }
        return $this;
    }
    public function getBillingPlanId()
    {
        return $this->getBillingPlan()->plan_id;
    }
    public function getBillingPlanData()
    {
        return $this->getBillingPlan()->data()->getAll();
    }

    public function insert($reload = true)
    {
        $table_name = $this->getTable()->getName();
        $max = $this->getAdapter()->selectCell("SELECT MAX(sort_order) FROM {$table_name}");
        $this->sort_order = $max + 1;
        return parent::insert($reload);
    }

    public function delete()
    {
        $ret = parent::delete();
        $this
            ->deleteFromRelatedTable('?_billing_plan')
            ->deleteFromRelatedTable('?_access')
            ->deleteFromRelatedTable('?_user_status')
            ->deleteFromRelatedTable('?_product_option')
            ->deleteFromRelatedTable('?_product_product_category');
        $this->getTable()->getAdapter()->query("DELETE FROM ?_resource_access WHERE fn='product_id' AND id=?d", $this->pk());

        $table_name = $this->getTable()->getName();
        $this->getAdapter()->query("UPDATE {$table_name}
            SET sort_order=sort_order-1
            WHERE sort_order>?", $this->sort_order);
        $this->getDi()->hook->call(Am_Event::PRODUCT_AFTER_DELETE, ['product' => $this]);
        return $ret;
    }
    /** @return array start date calculation rules */
    public function getStartDate()
    {
        $setting = $this->unserializeList(empty($this->start_date) ? '' : $this->start_date);
        if (!$setting)
            $setting = [
                self::SD_PRODUCT,
                self::SD_PAYMENT,
                self::SD_GROUP
            ];
        return $setting;
    }
    public function setStartDate(array $setting)
    {
        return $this->start_date = $this->serializeList($setting);
    }
    public function calculateStartDate($paymentDate, Invoice $invoice)
    {
        if ($paymentDate instanceof DateTime)
            $paymentDate = $paymentDate->format('Y-m-d');

        $ret = [];
        $setting = $this->getStartDate();
        $callArgs = [];
        if (in_array(self::SD_PRODUCT, $setting))
            $callArgs['product_id'] = $this->product_id;
        if (in_array(self::SD_GROUP, $setting))
            $callArgs['renewal_group'] = $this->renewal_group;
        if (in_array(self::SD_PAYMENT, $setting))
            $ret[] = $paymentDate;
        if (in_array(self::SD_FIXED, $setting) && $this->start_date_fixed)
            $ret[] = $this->start_date_fixed;
        if (count($callArgs))
        {
            $callArgs['user_id'] = $invoice->getUserId();
            $ret[] = $this->getDi()->accessTable->getLastExpire($callArgs);
        }
        foreach ([
                self::SD_WEEKDAY_SUN,
                self::SD_WEEKDAY_MON,
                self::SD_WEEKDAY_TUE,
                self::SD_WEEKDAY_WED,
                self::SD_WEEKDAY_THU,
                self::SD_WEEKDAY_FRI,
                self::SD_WEEKDAY_SAT
                 ] as $sd) {

            if (in_array($sd, $setting)) {
                preg_match('/w([0-6])/i', $sd, $matches);
                $need = $matches[1];

                /* @var $date DateTime */
                $date = $this->getDi()->dateTime;
                $now = $date->format('w');
                $diff = (7 + $need - $now) % 7;
                $date->modify("+{$diff} days");
                $ret[] = $date->format('Y-m-d');

            }
        }
        foreach ([
                self::SD_MONTH_1,
                self::SD_MONTH_15
                 ] as $sd) {

            if (in_array($sd, $setting)){
                preg_match('/m([0-6]+)/i', $sd, $matches);
                $need = $matches[1];

                /* @var $date DateTime */
                $date = $this->getDi()->dateTime;
                $now = $date->format('d');
                $m = ($now < $need) ? 0 : 1;
                $date->modify("+{$m} months");
                $ret[] = $date->format('Y-m-' . sprintf('%02d', $need));
            }
        }
        if (in_array(self::SD_THIS_MONTH_FIRST, $setting)) {
            $ret[] = date('Y-m-d', strtotime('first day of this month'));
        }
        if (in_array(self::SD_THIS_MONTH_LAST, $setting)) {
            $ret[] = date('Y-m-d', strtotime('last day of this month'));
        }
        if (in_array(self::SD_NEXT_DAY, $setting)) {
            $ret[] = date('Y-m-d', strtotime('tomorrow'));
        }

        $ret = array_filter($ret);
        if (!$ret) $ret[] = $this->getDi()->sqlDate;
        return max($ret);
    }

    public function addQty($requestedQty, $itemQty)
    {
        if (!$this->getIsCountable()) return 1;
        if ($this->getBillingPlan()->variable_qty)
            return $itemQty + $requestedQty;
        else
            return $this->getBillingPlan()->qty;
    }

    public function findItem(array $existingInvoiceItems)
    {
        foreach ($existingInvoiceItems as $item)
            if ($item->item_type == $this->getType() && $item->item_id == $this->getProductId())
                return $item;
    }

     /**
     *
     * @param string $req
     * @return array
     */
    protected function parseRequirementsGroup($req)
    {
        $catProducts = $this->getDi()->productCategoryTable->getCategoryProducts();
        $res = array_filter(explode(',',$req));
        foreach ($res as $k => $g) {
            if (preg_match('/CATEGORY-(\w*)-(\d*)/', $g, $match)) {
                unset($res[$k]);
                $status = $match[1];
                $catId = $match[2];
                foreach ($catProducts[$catId] as $prId) {
                    $res[] = sprintf('%s-%d', $status, $prId);
                }
            }
        }
        return $res;
    }

    function getRequireOther()
    {
        return array_unique(array_filter($this->parseRequirementsGroup($this->require_other)));
    }

    function getPreventIfOther()
    {
        return array_unique(array_filter($this->parseRequirementsGroup($this->prevent_if_other)));
    }


    public function getOptions($byKey=false)
    {
        if (empty($this->_optionsCache))
            $this->_optionsCache = $this->getDi()->productOptionTable->findByProductId($this->pk());
        if ($byKey) {
            $ret = [];
            foreach ($this->_optionsCache as $r)
                $ret[$r->name] = $r;
            return $ret;
        } else
            return $this->_optionsCache;
    }
    public function setOptions(array $options)
    {

    }
    /**
     * Change values of $options[first and second_price] according to option values $options
     * and product option prices settings
     * @param array $options
     * @param type $first_price
     * @param type $second_price
     */
    public function applyOptionsToPrices(array & $options)
    {
        $plan_id = $this->getBillingPlan()->pk();
        if (!$plan_id) return;
        foreach ($this->getOptions() as $opt)
        {
            if (!isset($options[$opt->name])) continue;
            $val = $options[$opt->name]['value'];
            $options[$opt->name]['first_price'] = null;
            $options[$opt->name]['second_price'] = null;
            foreach ((array)$val as $kk)
            {
                if ($pr = $opt->getPrices($plan_id, $kk))
                {
                    if (!empty($pr[0]))
                    {
                        $options[$opt->name]['first_price'] += moneyRound($pr[0]);
                    }
                    if (!empty($pr[1]))
                    {
                        $options[$opt->name]['second_price'] += moneyRound($pr[1]);
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Return URL to render in dashboard with product title or null if no link (default)
     * @return string|null
     */
    function getDashboardUrl()
    {
        if (!empty($this->url))
        {
            return $this->url;
        }
    }
}

/**
 * Products table
 * @package Am_Invoice
 */
class ProductTable extends Am_Table_WithData
{
    protected $_key = 'product_id';
    protected $_table = '?_product';
    protected $_recordClass = 'Product';

    protected $useCache = true;

    /*
     * Return array of products in the form
     * product_id => title
     * Suitable for usage in <SELECT>
     * @return array
     */
    function getOptions($onlyEnabled = false, $showArchived = false)
    {
        return $this->_db->selectCol("SELECT product_id as ARRAY_KEY, CONCAT('(', product_id, ') ', title)
            FROM ?_product
            WHERE is_archived < ?
            { AND is_disabled = ? }
            ORDER BY sort_order, title",
            $showArchived ? 2 : 1,
            $onlyEnabled ? 0 : DBSIMPLE_SKIP);
    }

    function getProductOptions()
    {
        $cOptions = [];
        foreach ($this->getDi()->productCategoryTable->getAdminSelectOptions() as $id => $title) {
            $cOptions['c' . $id] = $title;
        }
        if ($cOptions) {
            $options = [
                ___('Products') => $this->getOptions(),
                ___('Product Categories') => $cOptions
            ];
        } else {
            $options = $this->getDi()->productTable->getOptions();
        }
        return $options;
    }

    function extractProductIds($p)
    {
        $ret = [];
        $categoryProduct = $this->getDi()->productCategoryTable->getCategoryProducts();
        foreach($p as $id) {
            if (preg_match('/^c([0-9]*)$/i', $id, $m)) {
                $ret = array_merge($ret, $categoryProduct[$m[1]]);
            } else {
                $ret[] = $id;
            }
        }
        return array_map('intval', $ret);
    }

    /**
     * Return products that are visible in passed context
     * @param array $productCategories or null to do not limit
     * @param array|null $activeProductIds active products of the current user or null to skip checks
     * @param array|null $expiredProductIds active products of the current user or null to skip checks
     * @return array of Products
     */
    function getVisible($productCategories = null,
        $activeProductIds = null, $expiredProductIds = null)
    {
        if ($activeProductIds === null) {
            // skip check for product requirements
        }
        if ($productCategories)
            $productCategories = array_filter(array_map('intval', $productCategories));

        return $this->selectObjects("SELECT p.*
                                    FROM  ?_product p
                                    {RIGHT JOIN ?_product_product_category ppc
                                    ON p.product_id=ppc.product_id AND ppc.product_category_id in (?a)}
                                    WHERE   p.product_id > 0 AND p.is_disabled = 0 AND p.is_archived = 0
                                    GROUP BY p.product_id
                                    ORDER BY p.sort_order, p.title
            ",  $productCategories ? $productCategories : DBSIMPLE_SKIP);
    }

    /**
     * @param array of #ids
     * @return array of strings
     */
    function getProductTitles(array $ids) {
        return ($ids = array_filter(array_map('intval', $ids))) ?
                    array_map(['Product', 'stripEscape'],
                        $this->_db->selectCol("SELECT product_id as ARRAY_KEY, title FROM ?_product WHERE product_id IN (?a)",
                        $ids)) :
                    [];
    }

    /**
     * Filter products that should not be available on signup/renew pages depends on
     * require_other and prevent_if_other product settings
     * @param array $products Product objects that are purchasing now
     * @param array $haveActiveIds int product# user has active subscriptions to
     * @param array $haveExpiredIds int product# user has expired subscriptions to
     * @param bool $select_multiple - is user able to select multiple products on signup form.
     * @return Product[] $products Array of products.
     */
    function filterProducts(array $products, array $haveActiveIds = [], array $haveExpiredIds= [], $select_multiple=false){
        $have = array_unique(array_merge(
                array_map(function($id) {return "ACTIVE-$id";}, $haveActiveIds),
                array_map(function($id) {return "EXPIRED-$id";}, $haveExpiredIds)
        ));
        do{
            $changes =0; $all_available = [];
            foreach($products as $pr){
                $all_available[] = 'ACTIVE-'.$pr->product_id;
            }
            foreach($products as $k=>$v){
                $po = $v->getPreventIfOther();
                $ro = $v->getRequireOther();
                if($po && array_intersect($po, $have))
                {
                    unset($products[$k]);
                    $changes++;
                }

                if($ro){
                    if(is_array($have) && $have){
                        if(!array_intersect($ro, ($select_multiple ? array_merge($have, $all_available) : $have))){
                            unset($products[$k]);
                            $changes++;
                        }
                    }else{
                        if(!array_intersect($ro, ($select_multiple  ? $all_available : []))){
                            unset($products[$k]);
                            $changes++;
                        }
                    }
                }
            }
        }while($changes);
        return $products;
    }

    /**
     * Check if require_other prevent_if_other product settings are statisfied
     * for current purchase
     * @param array $products Product objects that are purchasing now
     * @param array $haveActiveIds int product# user has active subscriptions to
     * @param array $haveExpiredIds int product# user has expired subscriptions to
     * @return array empty array of OK, or an array full of error messages
     */
    function checkRequirements(array $products, array $haveActiveIds = [], array $haveExpiredIds = []){
        $error = [];
        $have = array_unique(array_merge(
                array_map(function($id) {return "ACTIVE-$id";}, $haveActiveIds),
                array_map(function($id) {return "EXPIRED-$id";}, $haveExpiredIds)
        ));
        $will_have = array_unique(array_merge(
                $have,
                array_map(function(Product $p) {return "ACTIVE-".$p->product_id;}, $products)
        ));

        foreach ($products as $pr){
            if ($ro = $pr->getRequireOther()){
                if ($ro && !array_intersect($ro, $will_have)) {
                    $ids = [];
                    foreach ($ro as $s)
                        if (preg_match('/^ACTIVE-(\d+)$/', $s, $args)) $ids[] = $args[1];
                    if ($ids){
                        $error[] = sprintf(___('"%s" can be ordered along with these products/subscription(s) only: %s'), $pr->getTitle(true),
                            implode(',', $this->getProductTitles($ids)));
                        continue;
                    }
                    $ids = [];
                    foreach ($ro as $s)
                        if (preg_match('/^EXPIRED-(\d+)$/', $s, $args)) $ids[] = $args[1];
                    if ($ids){
                        $error[] = sprintf(___('"%s" can only be ordered if you have expired subscription(s) for these products: %s'), $pr->getTitle(true),
                            implode(',',$this->getProductTitles($ids)));
                        continue;
                    }
                }
            }
            if ($ro = $pr->getPreventIfOther()){
                if ($ro && array_intersect($ro, $have)) {
                    $ids = [];
                    foreach ($ro as $s)
                        if (preg_match('/^ACTIVE-(\d+)$/', $s, $args)) $ids[] = $args[1];

                    $ids = array_intersect($ids, $haveActiveIds);
                    if ($ids)
                    {
                        $error[] = sprintf(___('"%s" cannot be ordered because you have active subscription(s) to: %s'), $pr->getTitle(true),
                            implode(',',$this->getProductTitles($ids)));
                        continue;
                    }
                    $ids = [];
                    foreach ($ro as $s)
                        if (preg_match('/^EXPIRED-(\d+)$/', $s, $args)) $ids[] = $args[1];

                    $ids = array_intersect($ids, $haveExpiredIds);
                    if ($ids)
                    {
                        $error[] = sprintf(___('"%s" cannot be ordered because you have expired subscription(s) to: %s'), $pr->getTitle(true),
                            implode(',',$this->getProductTitles($ids)));
                        continue;
                    }
                }
            }
        }
        return $error;
    }

    /**
     * return query object with category filter applied if specified
     * if parameters === 0, it selects products not assigned to any categories
     * if parameter === null, it selects products regardless of categories
     * @param int $product_category_id
     * @param bool|array $include_hidden  Include products from hidden categories,
     * in case of array include hidden categories only with given in array codes.
     * @return Am_Query
     */
    function createQuery($product_category_id = null, $include_hidden=true, $scope = false)
    {
        $q = new Am_Query($this, 'p');
        $q->addOrder('sort_order')->addOrder('title');
        $q->addWhere('p.is_disabled=0');
        $q->addWhere('p.is_archived=0');
        if ($scope) {
            $q->addHaving('SUM(IF(ppc.product_category_id IN (?a), 1, 0)) > 0', $scope);
        }

        $q->leftJoin('?_product_product_category', 'ppc', 'ppc.product_id = p.product_id')
            ->leftJoin('?_product_category', 'pc', 'pc.product_category_id = ppc.product_category_id');

        if (is_array($include_hidden)) {
            $include_hidden[] = -1; //case of empty array
            //Product is not member of any category with code OR member of at least one category with revealed code
            $q->addHaving('(SUM(IF(pc.code>"" AND pc.code NOT IN (?a), 1, 0)) = 0 OR SUM(IF(pc.code>"" AND pc.code IN (?a), 1, 0)) >= 1)', $include_hidden, $include_hidden);
        } elseif (!$include_hidden) {
            $q->addHaving('SUM(IF(pc.code>"", 1, 0)) = 0');
        }

        if ($product_category_id > 0) {
            $q->addHaving('SUM(IF(pc.product_category_id=?, 1, 0)) > 0', $product_category_id);
        } elseif ((string)$product_category_id === '0') {
            $q->addHaving('COUNT(ppc.product_category_id)=0');
        }

        return $q;
    }

    function getRenewalGroups()
    {
        return $this->_db->selectCol("SELECT DISTINCT renewal_group, renewal_group AS ?
            FROM ?_product
            WHERE renewal_group <> ''
            ORDER BY renewal_group", DBSIMPLE_ARRAY_KEY);
    }
}