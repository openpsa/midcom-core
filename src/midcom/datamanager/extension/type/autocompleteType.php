<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 */

namespace midcom\datamanager\extension\type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\Form\AbstractType;
use midcom\datamanager\extension\transformer\autocompleteTransformer;
use midcom\datamanager\extension\transformer\jsonTransformer;
use midcom\datamanager\extension\transformer\multipleTransformer;
use midcom\datamanager\extension\helper;
use midcom\datamanager\helper\autocomplete as autocomplete_helper;
use midcom_error;
use midcom_connection;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\FormType;

/**
 * Experimental autocomplete type
 */
class autocompleteType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('error_bubbling', false);
        $resolver->setNormalizer('widget_config', function (Options $options, $value) {
            $widget_defaults = [
                'creation_mode_enabled' => false,
                'class' => null,
                'component' => null, // unused, for backward-compat only
                'id_field' => 'guid',
                'constraints' => [],
                'result_headers' => [],
                'orders' => [],
                'auto_wildcards' => 'both',
                'creation_handler' => null,
                'creation_default_key' => null,
                'titlefield' => null,
                'categorize_by_parent_label' => false,
                'searchfields' => [],
                'min_chars' => 2,
                'handler_url' => midcom_connection::get_url('self') . 'midcom-exec-midcom.datamanager/autocomplete.php',
                'sortable' => false,
                'clever_class' => null
            ];

            if (!empty($value['clever_class'])) {
                /** @var \midcom_helper_configuration $config */
                $config = \midcom_baseclasses_components_configuration::get('midcom.datamanager', 'config');
                $config = $config->get_array('clever_classes');
                if (!array_key_exists($value['clever_class'], $config)) {
                    throw new midcom_error('Invalid clever class specified');
                }
                $value = array_merge($config[$value['clever_class']], $value);
            }

            return helper::normalize($widget_defaults, $value);
        });
        $resolver->setNormalizer('type_config', function (Options $options, $value) {
            $type_defaults = [
                'options' => [],
                'method' => 'GET',
                'constraints' => [],
                'allow_other' => false,
                'allow_multiple' => ($options['dm2_type'] == 'mnrelation'),
                'require_corresponding_option' => true,
                'multiple_storagemode' => 'serialized',
                'multiple_separator' => '|'
            ];

            $resolved = helper::normalize($type_defaults, $value);
            if (empty($resolved['constraints']) && !empty($options['widget_config']['constraints'])) {
                $resolved['constraints'] = $options['widget_config']['constraints'];
            }
            return $resolved;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new autocompleteTransformer($options));
        $builder->add('selection', HiddenType::class);
        $builder->get('selection')->addViewTransformer(new jsonTransformer);

        if ($options['type_config']['allow_multiple'] && $options['dm2_type'] == 'select') {
            $builder->get('selection')->addModelTransformer(new multipleTransformer($options));
        }

        $builder->add('search_input', SearchType::class, ['mapped' => false]);
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $preset = [];
        if (!empty($view->children['selection']->vars['data'])) {
            foreach (array_filter((array) $view->children['selection']->vars['data']) as $identifier) {
                if ($options['widget_config']['id_field'] == 'id') {
                    $identifier = (int) $identifier;
                }
                try {
                    $object = $options['widget_config']['class']::get_cached($identifier);
                    $preset[$identifier] = autocomplete_helper::create_item_label($object, $options['widget_config']['result_headers'], $options['widget_config']['titlefield']);
                } catch (midcom_error $e) {
                    $e->log();
                }
            }
        }

        $handler_options = array_replace([
            'method' => $options['type_config']['method'],
            'allow_multiple' => $options['type_config']['allow_multiple'],
            'preset' => $preset,
            'preset_order' => array_reverse(array_keys($preset))
        ], $options['widget_config']);

        // @todo widget_config constraints are ignored, but they are used in the wild...
        $handler_options['constraints'] = $options['type_config']['constraints'];

        $view->vars['min_chars'] = $options['widget_config']['min_chars'];
        $view->vars['handler_options'] = $handler_options;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'autocomplete';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return FormType::class;
    }
}
