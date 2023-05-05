# Platial k-anonymity

This repository contains code and data for the following paper:

> McKenzie, G., Zhang, H. (2023).  **Platial *k*-anonymity: Improving location anonymity through temporal popularity signatures.** *Proceedings of the 12th International Conference on Geographic Information Science ([GIScience '23](https://giscience.org/)).* 11. LIPIcs. 10.4230/LIPIcs.GIScience.2023.11

## Data

Due to [twitter's terms of use](https://developer.twitter.com/en/developer-terms/more-on-restricted-use-cases), the source data only contains tweet identifiers.  A tool such as [twarc](https://twarc-project.readthedocs.io/en/latest/) can be used to hydrate these identifiers in order to get the contents, extract the swarm check-in URL from the contents, and get the foursquare venue identifier.  The scripts to do this are in the scripts directory.