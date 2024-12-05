<?php

namespace IFix\Testing\FixtureLoader;

enum PostPersist
{
    case None;
    case Clear;
    case Refresh;
}
