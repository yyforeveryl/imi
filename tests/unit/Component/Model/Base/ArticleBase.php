<?php

namespace Imi\Test\Component\Model\Base;

use Imi\Model\Annotation\Column;
use Imi\Model\Annotation\DDL;
use Imi\Model\Annotation\Entity;
use Imi\Model\Annotation\Table;
use Imi\Model\Model as Model;

/**
 * tb_article 基类.
 *
 * @Entity
 * @Table(name="tb_article", id={"id"})
 * @DDL("CREATE TABLE `tb_article` (   `id` int(10) unsigned NOT NULL AUTO_INCREMENT,   `title` varchar(255) NOT NULL,   `content` mediumtext NOT NULL,   `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,   PRIMARY KEY (`id`) USING BTREE ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT")
 *
 * @property int    $id
 * @property string $title
 * @property string $content
 * @property string $time
 */
abstract class ArticleBase extends Model
{
    /**
     * id.
     *
     * @Column(name="id", type="int", length=10, accuracy=0, nullable=false, default="", isPrimaryKey=true, primaryKeyIndex=0, isAutoIncrement=true)
     *
     * @var int
     */
    protected $id;

    /**
     * 获取 id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 赋值 id.
     *
     * @param int $id id
     *
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * title.
     *
     * @Column(name="title", type="varchar", length=255, accuracy=0, nullable=false, default="", isPrimaryKey=false, primaryKeyIndex=-1, isAutoIncrement=false)
     *
     * @var string
     */
    protected $title;

    /**
     * 获取 title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * 赋值 title.
     *
     * @param string $title title
     *
     * @return static
     */
    public function setTitle($title)
    {
        if (isset($title[254]))
        {
            throw new \InvalidArgumentException('The maximum length of $title is 255');
        }
        $this->title = $title;

        return $this;
    }

    /**
     * content.
     *
     * @Column(name="content", type="mediumtext", length=0, accuracy=0, nullable=false, default="", isPrimaryKey=false, primaryKeyIndex=-1, isAutoIncrement=false)
     *
     * @var string
     */
    protected $content;

    /**
     * 获取 content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * 赋值 content.
     *
     * @param string $content content
     *
     * @return static
     */
    public function setContent($content)
    {
        if (isset($content[20]))
        {
            throw new \InvalidArgumentException('The maximum length of $content is 21');
        }
        $this->content = $content;

        return $this;
    }

    /**
     * time.
     *
     * @Column(name="time", type="timestamp", length=0, accuracy=0, nullable=false, default="CURRENT_TIMESTAMP", isPrimaryKey=false, primaryKeyIndex=-1, isAutoIncrement=false)
     *
     * @var string
     */
    protected $time;

    /**
     * 获取 time.
     *
     * @return string
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * 赋值 time.
     *
     * @param string $time time
     *
     * @return static
     */
    public function setTime($time)
    {
        $this->time = $time;

        return $this;
    }
}
