import { InstrumentationBase, InstrumentationNodeModuleDefinition } from '@opentelemetry/instrumentation';
import { SDK_VERSION, getCurrentScope, OPENAI_INTEGRATION_NAME, instrumentOpenAiClient } from '@sentry/core';

const supportedVersions = ['>=4.0.0 <6'];

/**
 * Determines telemetry recording settings.
 */
function determineRecordingSettings(
  integrationOptions,
  defaultEnabled,
) {
  const recordInputs = integrationOptions?.recordInputs ?? defaultEnabled;
  const recordOutputs = integrationOptions?.recordOutputs ?? defaultEnabled;
  return { recordInputs, recordOutputs };
}

/**
 * Sentry OpenAI instrumentation using OpenTelemetry.
 */
class SentryOpenAiInstrumentation extends InstrumentationBase {
   constructor(config = {}) {
    super('@sentry/instrumentation-openai', SDK_VERSION, config);
  }

  /**
   * Initializes the instrumentation by defining the modules to be patched.
   */
   init() {
    const module = new InstrumentationNodeModuleDefinition('openai', supportedVersions, this._patch.bind(this));
    return module;
  }

  /**
   * Core patch logic applying instrumentation to the OpenAI client constructor.
   */
   _patch(exports) {
    const Original = exports.OpenAI;

    const WrappedOpenAI = function ( ...args) {
      const instance = Reflect.construct(Original, args);
      const scopeClient = getCurrentScope().getClient();
      const integration = scopeClient?.getIntegrationByName(OPENAI_INTEGRATION_NAME);
      const integrationOpts = integration?.options;
      const defaultPii = Boolean(scopeClient?.getOptions().sendDefaultPii);

      const { recordInputs, recordOutputs } = determineRecordingSettings(integrationOpts, defaultPii);

      return instrumentOpenAiClient(instance , {
        recordInputs,
        recordOutputs,
      });
    } ;

    // Preserve static and prototype chains
    Object.setPrototypeOf(WrappedOpenAI, Original);
    Object.setPrototypeOf(WrappedOpenAI.prototype, Original.prototype);

    for (const key of Object.getOwnPropertyNames(Original)) {
      if (!['length', 'name', 'prototype'].includes(key)) {
        const descriptor = Object.getOwnPropertyDescriptor(Original, key);
        if (descriptor) {
          Object.defineProperty(WrappedOpenAI, key, descriptor);
        }
      }
    }

    const isESM = Object.prototype.toString.call(exports) === '[object Module]';
    if (isESM) {
      exports.OpenAI = WrappedOpenAI;
      return exports;
    }

    return { ...exports, OpenAI: WrappedOpenAI };
  }
}

export { SentryOpenAiInstrumentation };
//# sourceMappingURL=instrumentation.js.map
