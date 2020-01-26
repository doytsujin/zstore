<?php

namespace App\Pages\Doc;

use \Zippy\Html\DataList\DataView;
use \Zippy\Html\Form\AutocompleteTextInput;
use \Zippy\Html\Form\Button;
use \Zippy\Html\Form\CheckBox;
use \Zippy\Html\Form\Date;
use \Zippy\Html\Form\DropDownChoice;
use \Zippy\Html\Form\Form;
use \Zippy\Html\Form\SubmitButton;
use \Zippy\Html\Form\TextInput;
use \Zippy\Html\Label;
use \Zippy\Html\Link\ClickLink;
use \Zippy\Html\Link\SubmitLink;
use \App\Entity\Customer;
use \App\Entity\Doc\Document;
use \App\Entity\Item;
use \App\Entity\Store;
use \App\Entity\MoneyFund;
use \App\Helper as H;
use \App\System;
use \App\Application as App;

/**
 * Страница  ввода  приходной  накладной
 */
class GoodsReceipt extends \App\Pages\Base {

    public $_itemlist = array();
    private $_doc;
    private $_basedocid = 0;
    private $_rowid = 0;

    public function __construct($docid = 0, $basedocid = 0) {
        parent::__construct();

        $common = System::getOptions("common");

        $this->add(new Form('docform'));
        $this->docform->add(new TextInput('document_number'));
        $this->docform->add(new Date('document_date'))->setDate(time());
        $this->docform->add(new AutocompleteTextInput('customer'))->onText($this, 'OnAutoCustomer');

        $this->docform->add(new DropDownChoice('store', Store::getList(), H::getDefStore()));
        $this->docform->add(new TextInput('notes'));
        $this->docform->add(new TextInput('basedoc'));

        $this->docform->add(new TextInput('barcode'));
        $this->docform->add(new SubmitLink('addcode'))->onClick($this, 'addcodeOnClick');

        $this->docform->add(new DropDownChoice('payment', MoneyFund::getList(true, true), H::getDefMF()))->onChange($this, 'OnPayment');



        $this->docform->add(new DropDownChoice('val', array(1 => 'Гривна', 2 => 'Доллар', 3 => 'Евро', 4 => 'Рубль')))->onChange($this, "onVal", true);
        $this->docform->add(new Label('course', 'Курс 1'));
        $this->docform->val->setVisible($common['useval'] == true);
        $this->docform->course->setVisible($common['useval'] == true);

        $this->docform->add(new SubmitLink('addrow'))->onClick($this, 'addrowOnClick');
        $this->docform->add(new Button('backtolist'))->onClick($this, 'backtolistOnClick');
        $this->docform->add(new SubmitButton('savedoc'))->onClick($this, 'savedocOnClick');
        $this->docform->add(new SubmitButton('execdoc'))->onClick($this, 'savedocOnClick');
        $this->docform->add(new TextInput('editpayamount'));
        $this->docform->add(new SubmitButton('bpayamount'))->onClick($this, 'onPayAmount');
        $this->docform->add(new TextInput('editpayed', "0"));
        $this->docform->add(new SubmitButton('bpayed'))->onClick($this, 'onPayed');

        $this->docform->add(new Label('payed', 0));

        $this->docform->add(new Label('payamount', 0));

        $this->docform->add(new Label('total'));
        $this->docform->add(new \Zippy\Html\Form\File('scan'));

        $this->add(new Form('editdetail'))->setVisible(false);
        $this->editdetail->add(new AutocompleteTextInput('edititem'))->onText($this, 'OnAutoItem');
        $this->editdetail->add(new SubmitLink('addnewitem'))->onClick($this, 'addnewitemOnClick');
        $this->editdetail->add(new TextInput('editquantity'))->setText("1");
        $this->editdetail->add(new TextInput('editprice'));
        $this->editdetail->add(new TextInput('editsnumber'));
        $this->editdetail->add(new Date('editsdate'));

        $this->editdetail->add(new Button('cancelrow'))->onClick($this, 'cancelrowOnClick');
        $this->editdetail->add(new SubmitButton('saverow'))->onClick($this, 'saverowOnClick');

        //добавление нового товара
        $this->add(new Form('editnewitem'))->setVisible(false);
        $this->editnewitem->add(new TextInput('editnewitemname'));
        $this->editnewitem->add(new TextInput('editnewitemcode'));
        $this->editnewitem->add(new TextInput('editnewitembarcode'));
        $this->editnewitem->add(new TextInput('editnewitemsnumber'));
        $this->editnewitem->add(new TextInput('editnewitemsdate'));
        $this->editnewitem->add(new Button('cancelnewitem'))->onClick($this, 'cancelnewitemOnClick');
        $this->editnewitem->add(new SubmitButton('savenewitem'))->onClick($this, 'savenewitemOnClick');

        if ($docid > 0) {    //загружаем   содержимок  документа настраницу
            $this->_doc = Document::load($docid)->cast();
            $this->docform->document_number->setText($this->_doc->document_number);

            $this->docform->notes->setText($this->_doc->notes);
            $this->docform->basedoc->setText($this->_doc->basedoc);
            $this->docform->document_date->setDate($this->_doc->document_date);
            $this->docform->customer->setKey($this->_doc->customer_id);
            $this->docform->customer->setText($this->_doc->customer_name);
            $this->docform->payamount->setText($this->_doc->payamount);
            $this->docform->editpayamount->setText($this->_doc->payamount);
            $this->docform->payed->setText($this->_doc->payed);
            $this->docform->editpayed->setText($this->_doc->payed);
            $this->docform->store->setValue($this->_doc->headerdata['store']);
            $this->docform->payment->setValue($this->_doc->headerdata['payment']);


            $this->OnPayment($this->docform->payment);

            $this->docform->total->setText($this->_doc->amount);

            foreach ($this->_doc->detaildata as $item) {
                $item = new Item($item);
                $item->old = true;
                $this->_itemlist[$item->item_id] = $item;
            }
        } else {
            $this->_doc = Document::create('GoodsReceipt');
            $this->docform->document_number->setText($this->_doc->nextNumber());

            if ($basedocid > 0) {  //создание на  основании
                $basedoc = Document::load($basedocid);
                if ($basedoc instanceof Document) {
                    $this->_basedocid = $basedocid;
                    if ($basedoc->meta_name == 'OrderCust') {

                        $this->docform->customer->setKey($basedoc->customer_id);
                        $this->docform->customer->setText($basedoc->customer_name);

                        $order = $basedoc->cast();
                        $this->docform->basedoc->setText('Заказ ' . $order->document_number);
                        foreach ($order->detaildata as $_item) {
                            $item = new Item($_item);
                            $this->_itemlist[$item->item_id] = $item;
                        }
                        $this->CalcTotal();
                        $this->CalcPay();
                    }
                    if ($basedoc->meta_name == 'InvoiceCust') {

                        $this->docform->customer->setKey($basedoc->customer_id);
                        $this->docform->customer->setText($basedoc->customer_name);

                        $invoice = $basedoc->cast();
                        $this->docform->basedoc->setText('Счет ' . $invoice->document_number);
                        $this->docform->payment->setValue(\App\Entity\MoneyFund::PREPAID);


                        foreach ($invoice->detaildata as $_item) {
                            $item = new Item($_item);
                            $this->_itemlist[$item->item_id] = $item;
                        }
                        $this->CalcTotal();
                        $this->CalcPay();
                    }
                    $this->calcTotal();
                }
            }
        }

        $this->docform->add(new DataView('detail', new \Zippy\Html\DataList\ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_itemlist')), $this, 'detailOnRow'))->Reload();
        if (false == \App\ACL::checkShowDoc($this->_doc))
            return;
    }

    public function onVal($sender) {
        $val = $sender->getValue();
        $common = System::getOptions("common");

        if ($val == 1)
            $this->docform->course->setText('Курс 1');
        if ($val == 2)
            $this->docform->course->setText('Курс  ' . $common['cdoll']);
        if ($val == 3)
            $this->docform->course->setText('Курс  ' . $common['ceuro']);
        if ($val == 4)
            $this->docform->course->setText('Курс  ' . $common['crub']);

        $this->updateAjax(array('course'));
    }

    public function detailOnRow($row) {
        $item = $row->getDataItem();

        $row->add(new Label('item', $item->itemname));
        $row->add(new Label('code', $item->item_code));
        $row->add(new Label('quantity', H::fqty($item->quantity)));
        $row->add(new Label('price', H::fa($item->price)));
        $row->add(new Label('msr', $item->msr));
        $row->add(new Label('snumber', $item->snumber));
        $row->add(new Label('sdate', $item->sdate > 0 ? date('Y-m-d', $item->sdate) : ''));

        $row->add(new Label('amount', H::fa($item->quantity * $item->price)));
        $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
        $row->edit->setVisible($item->old != true);

        $row->add(new ClickLink('delete'))->onClick($this, 'deleteOnClick');
    }

    public function editOnClick($sender) {
        $item = $sender->getOwner()->getDataItem();
        $this->editdetail->setVisible(true);
        $this->docform->setVisible(false);

        $this->editdetail->editquantity->setText($item->quantity);
        $this->editdetail->editprice->setText($item->price);
        $this->editdetail->editsnumber->setText($item->snumber);
        $this->editdetail->editsdate->setDate($item->sdate);


        $this->editdetail->edititem->setKey($item->item_id);
        $this->editdetail->edititem->setText($item->itemname);


        $this->_rowid = $item->item_id;
    }

    public function deleteOnClick($sender) {
        if (false == \App\ACL::checkEditDoc($this->_doc))
            return;
        $item = $sender->owner->getDataItem();
        // unset($this->_itemlist[$item->item_id]);

        $this->_itemlist = array_diff_key($this->_itemlist, array($item->item_id => $this->_itemlist[$item->item_id]));
        $this->calcTotal();
        $this->calcPay();

        $this->docform->detail->Reload();
    }

    public function addcodeOnClick($sender) {
        $code = trim($this->docform->barcode->getText());
        $this->docform->barcode->setText('');
        if ($code == '')
            return;

        foreach ($this->_itemlist as $_item) {
            if ($_item->bar_code == $code) {
                $this->_itemlist[$_item->item_id]->quantity += 1;
                $this->docform->detail->Reload();
                $this->calcTotal();
                $this->CalcPay();
                return;
            }
        }


        $code = Item::qstr($code);
        $item = Item::getFirst("  (item_code = {$code} or bar_code = {$code})");

        $this->editdetail->setVisible(true);
        $this->docform->setVisible(false);
        $this->_rowid = 0;

        if ($item == null) {
            $this->setWarn('Товар не  найден');
        } else {
            $this->editdetail->edititem->setKey($item->item_id);
            $this->editdetail->edititem->setText($item->itemname);
            $this->editdetail->editprice->setText('');
        }
    }

    public function addrowOnClick($sender) {
        $this->editdetail->setVisible(true);
        $this->docform->setVisible(false);
        $this->_rowid = 0;
    }

    public function saverowOnClick($sender) {


        $id = $this->editdetail->edititem->getKey();
        $name = trim($this->editdetail->edititem->getText());
        if ($id == 0) {
            $this->setError("Не выбран товар");
            return;
        }


        $item = Item::load($id);


        $item->quantity = $this->editdetail->editquantity->getText();
        $item->price = $this->editdetail->editprice->getText();

        if ($item->price == 0) {
            $this->setWarn("Не указана цена");
        }
        $item->snumber = $this->editdetail->editsnumber->getText();

        if (strlen($item->snumber) == 0 && $item->useserial == 1 && $this->_tvars["usesnumber"] == true) {
            $this->setError("Товар требует ввода партии производителя");
            return;
        }


        $item->sdate = $this->editdetail->editsdate->getDate();
        if ($item->sdate == false)
            $item->sdate = '';
        if (strlen($item->snumber) > 0 && strlen($item->sdate) == 0) {
            $this->setError("К серии должна быть введена дата срока годности");
            return;
        }
        unset($this->_itemlist[$this->_rowid]);
        $this->_itemlist[$item->item_id] = $item;
        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
        $this->docform->detail->Reload();
        $this->calcTotal();
        $this->calcPay();

        //очищаем  форму
        $this->editdetail->edititem->setKey(0);
        $this->editdetail->edititem->setText('');

        $this->editdetail->editquantity->setText("1");

        $this->editdetail->editprice->setText("");
        $this->editdetail->editsnumber->setText("");
        $this->editdetail->editsdate->setText("");
        $this->goAnkor("lankor");
    }

    public function cancelrowOnClick($sender) {
        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
    }

    public function savedocOnClick($sender) {
        if (false == \App\ACL::checkEditDoc($this->_doc))
            return;

        $firm = H::getFirmData($this->_doc->branch_id);
        $this->_doc->headerdata["firmname"] = $firm['firmname'];

        $this->_doc->document_number = $this->docform->document_number->getText();
        $this->_doc->document_date = $this->docform->document_date->getDate();
        $this->_doc->notes = $this->docform->notes->getText();
        $this->_doc->customer_id = $this->docform->customer->getKey();
        if ($this->_doc->customer_id > 0) {
            $customer = Customer::load($this->_doc->customer_id);
            $this->_doc->headerdata['customer_name'] = $this->docform->customer->getText() . ' ' . $customer->phone;
        }
        $this->_doc->payamount = $this->docform->payamount->getText();
        $this->_doc->headerdata['store'] = $this->docform->store->getValue();
        $this->_doc->headerdata['payment'] = $this->docform->payment->getValue();
        $this->_doc->headerdata['basedoc'] = $this->docform->basedoc->getText();

        $this->_doc->payed = $this->docform->payed->getText();

        if ($this->_doc->headerdata['payment'] == \App\Entity\MoneyFund::PREPAID) {
            $this->_doc->payed = 0;
            $this->_doc->payamount = 0;
        }
        if ($this->_doc->headerdata['payment'] == \App\Entity\MoneyFund::CREDIT) {
            $this->_doc->payed = 0;
        }

        if ($this->checkForm() == false) {
            return;
        }

        $file = $this->docform->scan->getFile();
        if ($file['size'] > 10000000) {
            $this->setError("Файл больше 10М!");
            return;
        }

        $common = System::getOptions("common");
        foreach ($this->_itemlist as $item) {
            if ($item->old == true)
                continue;
            if ($common['useval'] != true)
                continue;

            if ($this->docform->val->getValue() == 2) {
                $item->price = round($item->price * $common['cdoll']);
                $item->curname = 'cdoll';
                $item->currate = $common['cdoll'];
            }
            if ($this->docform->val->getValue() == 3) {
                $item->price = round($item->price * $common['ceuro']);
                $item->curname = 'ceuro';
                $item->currate = $common['ceuro'];
            }
            if ($this->docform->val->getValue() == 4) {
                $item->price = round($item->price * $common['crub']);
                $item->curname = 'crub';
                $item->currate = $common['crub'];
            }
        }





        $this->_doc->detaildata = array();
        foreach ($this->_itemlist as $item) {
            $this->_doc->detaildata[] = $item->getData();
        }

        $this->_doc->amount = $this->docform->total->getText();
        $isEdited = $this->_doc->document_id > 0;


        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {
            if ($this->_basedocid > 0) {
                $this->_doc->parent_id = $this->_basedocid;
                $this->_basedocid = 0;
            }
            $this->_doc->save();

            if ($sender->id == 'execdoc') {
                if (!$isEdited)
                    $this->_doc->updateStatus(Document::STATE_NEW);

                $this->_doc->updateStatus(Document::STATE_EXECUTED);

                if ($this->_doc->parent_id > 0) {   //закрываем заказ
                    if ($this->_doc->payamount > 0 && $this->_doc->payamount > $this->_doc->payed) {
                        
                    } else {
                        $order = Document::load($this->_doc->parent_id);
                        if ($order->state == Document::STATE_INPROCESS) {
                            $order->updateStatus(Document::STATE_CLOSED);
                            $this->setSuccess("Заказ {$order->document_number} закрыт");
                        }
                    }
                }
            } else {

                $this->_doc->updateStatus($isEdited ? Document::STATE_EDITED : Document::STATE_NEW);
            }


            if ($file['size'] > 0) {
                H::addFile($file, $this->_doc->document_id, 'Скан', \App\Entity\Message::TYPE_DOC);
            }

            //если  выполнен и оплачен
            if ($this->_doc->state == Document::STATE_EXECUTED && $this->_doc->payment > 0 && $this->_doc->payed == $this->_doc->payment) {
                $orders = $this->_doc->getChildren('OrderCust');
                foreach ($orders as $order) {
                    if ($order->state == Document::STATE_INPROCESS) {
                        //закрываем заявку
                        $order->updateStatus(Document::STATE_CLOSED);
                    }
                }
            }


            $conn->CommitTrans();
        } catch (\Exception $ee) {
            global $logger;
            $conn->RollbackTrans();
            $this->setError($ee->getMessage());
            $logger->error($ee->getMessage() . " Документ " . $this->_doc->meta_desc);

            return;
        }
        App::RedirectBack();
    }

    public function onPayAmount($sender) {


        $this->docform->payamount->setText($this->docform->editpayamount->getText());
        $this->docform->payed->setText($this->docform->editpayamount->getText());
        $this->docform->editpayed->setText($this->docform->editpayamount->getText());
        $this->goAnkor("tankor");
    }

    public function onPayed($sender) {
        $this->docform->payed->setText(H::fa($this->docform->editpayed->getText()));
        $this->goAnkor("tankor");
    }

    public function OnPayment($sender) {
        $this->docform->payed->setVisible(true);
        $this->docform->payamount->setVisible(true);


        $b = $sender->getValue();


        if ($b == \App\Entity\MoneyFund::PREPAID) {
            $this->docform->payed->setVisible(false);
            $this->docform->payamount->setVisible(false);
        }
        if ($b == \App\Entity\MoneyFund::CREDIT) {
            $this->docform->payed->setVisible(false);
        }
    }

    /**
     * Расчет  итого
     *
     */
    private function calcTotal() {

        $total = 0;

        foreach ($this->_itemlist as $item) {
            $item->amount = $item->price * $item->quantity;
            $total = $total + $item->amount;
        }
        $this->docform->total->setText(round($total));
    }

    private function CalcPay() {
        $total = $this->docform->total->getText();
        $this->docform->editpayamount->setText(round($total));
        $this->docform->payamount->setText(round($total));
        $this->docform->editpayed->setText(round($total));
        $this->docform->payed->setText(round($total));
    }

    /**
     * Валидация   формы
     *
     */
    private function checkForm() {
        if (strlen($this->_doc->document_number) == 0) {
            $this->setError('Введите номер документа');
        }
        if (count($this->_itemlist) == 0) {
            $this->setError("Не введен ни один  товар");
        }
        if (($this->docform->store->getValue() > 0 ) == false) {
            $this->setError("Не выбран  склад");
        }
        if ($this->docform->customer->getKey() == 0) {
            $this->setError("Не выбран  поставщик");
        }
        if ($this->docform->payment->getValue() == 0) {
            $this->setError("Не указан  способ  оплаты");
        }
        return !$this->isError();
    }

    public function backtolistOnClick($sender) {
        App::RedirectBack();
    }

    public function OnAutoItem($sender) {

        $text = trim($sender->getText());
        return Item::findArrayAC($text);
    }

    public function OnAutoCustomer($sender) {
        $text = Customer::qstr('%' . $sender->getText() . '%');
        return Customer::findArray("customer_name", "status=0 and   (customer_name like {$text}  or phone like {$text} )");
    }

    //добавление нового товара
    public function addnewitemOnClick($sender) {
        $this->editnewitem->setVisible(true);
        $this->editdetail->setVisible(false);

        $this->editnewitem->clean();


        if (System::getOption("common", "autoarticle") == 1) {
            $this->editnewitem->editnewitemcode->setText(Item::getNextArticle());
        }
    }

    public function savenewitemOnClick($sender) {
        $itemname = trim($this->editnewitem->editnewitemname->getText());
        if (strlen($itemname) == 0) {
            $this->setError("Не введено имя");
            return;
        }
        $item = new Item();
        $item->itemname = $itemname;
        $item->item_code = $this->editnewitem->editnewitemcode->getText();
        $item->save();
        $this->editdetail->edititem->setText($item->itemname);
        $this->editdetail->edititem->setKey($item->item_id);

        $this->editnewitem->setVisible(false);
        $this->editdetail->setVisible(true);
    }

    public function cancelnewitemOnClick($sender) {
        $this->editnewitem->setVisible(false);
        $this->editdetail->setVisible(true);
    }

}
