<?php
declare(strict_types=1);

use Automotive\Garage\Model\Vehicle;
use Automotive\Garage\Test\Support\TemplateRenderer;

$tests['account template renders the empty state and required manual fields'] = static function (): void {
    $html = TemplateRenderer::render([]);
    same(true, str_contains($html, 'Your garage is empty.'));
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    same(3, $xpath->query('//input[@required]')->length);
    same(1, $xpath->query('//input[@name="registration"]')->length);
    same(1, $xpath->query('//input[@name="make"]')->length);
    same(1, $xpath->query('//input[@name="model"]')->length);
};
$tests['all rendered mutations are POST forms with form keys and contextual labels'] = static function (): void {
    $vehicles = [new Vehicle(1, 'AB12CDE', 'Ford', 'Ranger', '', true, true),
        new Vehicle(2, 'XY20ABC', 'Toyota', 'Hilux', 'Work', false, false)];
    $html = TemplateRenderer::render($vehicles);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    same(5, $xpath->query('//form')->length);
    same(0, $xpath->query('//form[not(@method="post")]')->length);
    same(0, $xpath->query('//form[not(.//input[@name="form_key"])]')->length);
    same(4, $xpath->query('//form//button[@aria-label]')->length);
};
$tests['stored and rejected form text cannot create HTML elements or injected attributes'] = static function (): void {
    $attack = '<script id="garage-attack">alert(1)</script>';
    $attribute = '" autofocus onfocus="alert(1)';
    $vehicle = new Vehicle(1, 'AB12CDE', '<img src=x onerror=alert(1)>', $attribute, $attack, true, true);
    $html = TemplateRenderer::render([$vehicle], ['vehicle_id' => 1, 'registration' => $attribute,
        'make' => $attack, 'model' => $attribute, 'nickname' => $attack], true);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    same(0, $xpath->query('//script|//img|//*[@autofocus]|//*[@onfocus]|//*[@onerror]')->length);
    same($attribute, $xpath->query('//input[@name="registration"]')->item(0)->getAttribute('value'));
    same($attack, $xpath->query('//input[@name="make"]')->item(0)->getAttribute('value'));
    same(true, str_contains($html, 'Save changes'));
    same(true, str_contains($html, 'Cancel editing'));
};
