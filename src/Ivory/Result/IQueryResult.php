<?php
namespace Ivory\Result;

interface IQueryResult
{
    function filter();

    function map();

    function project();

    function hash();
}
