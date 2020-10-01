<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 */

namespace midcom\datamanager\extension\type;

use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use midcom\datamanager\extension\helper;
use midcom\datamanager\validation\php;
use Symfony\Component\Form\AbstractType;

/**
 * Experimental codemirror type
 */
class codemirrorType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $map_attr = function (Options $options, $value) {
            if ($value === null) {
                $value = [];
            }
            $value['rows'] = !empty($options['widget_config']['height']) ? $options['widget_config']['height'] : 6;
            $value['cols'] = !empty($options['widget_config']['width']) ? $options['widget_config']['width'] : 50;
            $value['class'] = $options['widget_config']['enabled'] ? 'codemirror ' . $options['widget_config']['language'] : 'longtext';
            return $value;
        };
        $resolver->setDefault('attr', $map_attr);

        helper::add_normalizers($resolver, [
            'widget_config' => [
                'enabled' => true,
                'language' => 'php',
            ],
            'type_config' => [
                'output_mode' => 'code',
                'modes' => ['xml', 'javascript', 'css', 'clike', 'php'],
            ]
        ]);

        $resolver->setNormalizer('constraints', function (Options $options, $value) {
            if ($options['dm2_type'] == 'php') {
                $value[] = new php;
            }
            return $value;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['widget_config']['enabled']) {
            $config = \midcom_baseclasses_components_configuration::get('midcom.datamanager', 'config');
            $view->vars['codemirror_snippet'] = \midcom_helper_misc::get_snippet_content_graceful($config->get('codemirror_config_snippet'));
            $view->vars['modes'] = $options['type_config']['modes'];

        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'codemirror';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return TextareaType::class;
    }
}
