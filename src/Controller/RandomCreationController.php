<?php

/**
 * This file is part of the GoGoCarto project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2016 Sebastian Castro - 90scastro@gmail.com
 * @license    MIT License
 * @Last Modified time: 2018-06-05 18:12:19
 */

namespace App\Controller;

use App\Services\RandomCreationService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class RandomCreationController extends Controller
{
    public function generateAction($nombre, $generateVote = false, RandomCreationService $randomService)
    {
        $lastElementCreated = $randomService->generate($nombre, $generateVote);

        return new Response('Elements générés');
    }
}