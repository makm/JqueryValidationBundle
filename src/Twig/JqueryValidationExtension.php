<?php

namespace Boekkooi\Bundle\JqueryValidationBundle\Twig;

use Boekkooi\Bundle\JqueryValidationBundle\Form\FormRuleContext;
use Boekkooi\Bundle\JqueryValidationBundle\Form\Util\FormHelper;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @author Warnar Boekkooi <warnar@boekkooi.net>
 */
class JqueryValidationExtension extends AbstractExtension
{
    /**
     * {@inheritDoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction(
                'form_jquery_validation',
                [$this, 'renderJavascript'],
                ['needs_environment' => true, 'pre_escape' => ['html', 'js'], 'is_safe' => ['html', 'js']]
            ),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'boekkooi_jquery_validation';
    }

    /**
     * @param Environment $twig
     * @param FormView    $view
     * @return string|string[]|null
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function renderJavascript(Environment $twig, FormView $view)
    {
        if (!isset($view->vars['rule_context'])) {
            return '';
        }

        /** @var \Boekkooi\Bundle\JqueryValidationBundle\Form\FormRuleContext $rootContext */
        $template = '@BoekkooiJqueryValidation/Form/form_validate.js.twig';
        $rootContext = $context = $view->vars['rule_context'];
        $rootView = $view;

        // The given view is not the root form
        if ($view->parent !== null) {
            $template = '@BoekkooiJqueryValidation/Form/dynamic_validate.js.twig';
            $rootView = FormHelper::getViewRoot($view);
            $rootContext = $rootView->vars['rule_context'];
        }

        // Create template variables
        $templateVars = [
            'form'              => $rootView,
            'fields'            => $this->fieldRulesViewData($context),
            'validation_groups' => $this->validationGroupsViewData($rootContext),
        ];
        $templateVars['enforce_validation_groups'] = count($templateVars['validation_groups']) > 1;
        $templateVars['enabled_validation_groups'] = count($rootContext->getButtons()) === 0 ? $templateVars['validation_groups'] : [];

        // Only add buttons from the root form
        if ($view->parent === null) {
            $templateVars['buttons'] = $this->buttonsViewData($context);
        }

        $js = $twig->render($template, $templateVars);

        return preg_replace('/\s+/', ' ', $js);
    }

    protected function validationGroupsViewData(FormRuleContext $context): array
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveArrayIterator($context->getGroups())
        );

        return array_unique(array_filter(iterator_to_array($it, false)));
    }

    /**
     * Transform the buttons in the given form into a array that can easily be used by twig.
     *
     * @param FormRuleContext $context
     * @return array
     */
    protected function buttonsViewData(FormRuleContext $context): array
    {
        $buttonNames = $context->getButtons();

        $buttons = [];
        foreach ($buttonNames as $name) {
            $groups = $context->getGroup($name);

            $buttons[] = [
                'name'              => $name,
                'cancel'            => count($groups) === 0,
                'validation_groups' => $groups,
            ];
        }

        return $buttons;
    }

    protected function fieldRulesViewData(FormRuleContext $context): array
    {
        $fields = [];
        foreach ($context->all() as $name => $rules) {
            $fields[] = [
                'name'  => $name,
                'rules' => $rules,
            ];
        }

        return $fields;
    }
}
